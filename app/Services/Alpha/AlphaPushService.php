<?php

declare(strict_types=1);

namespace App\Services\Alpha;

use App\Config\SecretsConfig;
use App\Models\ApiRepositories\Alpha\AlphaPushRepository;

/**
 * Alpha Web Push 送信サービス（ペイロード無し tickle 方式）。
 *
 * RFC8291 のペイロード暗号化は行わない。VAPID (RFC8292) の ES256 JWT を付けた
 * 空POSTを push エンドポイントへ送るだけ（tickle）。通知の中身は Service Worker 側が
 * /alpha-api/alerts を fetch して表示する。
 *
 * - 外部ライブラリ不使用（openssl / curl のみ）。
 * - openssl_sign は DER 形式の ECDSA 署名を返すので、JOSE 用に raw r||s 64byte へ変換する。
 * - JWT は audience（endpoint の scheme://host）ごとに1実行内でキャッシュして再利用。
 * - 404/410（購読失効）は購読を即削除。その他の失敗は fail_count++（5回で削除）。
 */
class AlphaPushService
{
    private const JWT_TTL_SEC = 43200; // 12h（RFC8292 の上限 24h 以内）
    private const REQUEST_TIMEOUT_SEC = 10;

    /** @var array<string, string> audience => JWT のキャッシュ（1実行内） */
    private array $jwtCache = [];

    public function __construct(
        private AlphaPushRepository $repo,
    ) {
    }

    /**
     * 対象ユーザー群の全購読へ tickle を送信し、結果を集計して返す。
     * 購読単位で1回ずつ送る（同一購読への重複送信なし）。VAPID 未設定なら何もしない。
     *
     * @param string[] $userIds
     * @return array{subscriptions:int, sent:int, removed:int, failed:int}
     */
    public function notifyUsers(array $userIds): array
    {
        $result = ['subscriptions' => 0, 'sent' => 0, 'removed' => 0, 'failed' => 0];
        if (!SecretsConfig::isVapidConfigured() || empty($userIds)) {
            return $result;
        }

        $subscriptions = $this->repo->getSubscriptionsByUserIds($userIds);
        $result['subscriptions'] = count($subscriptions);

        foreach ($subscriptions as $sub) {
            $status = $this->postTickle($sub['endpoint']);
            if ($status >= 200 && $status < 300) {
                $this->repo->markSent($sub['id']);
                $result['sent']++;
            } elseif ($status === 404 || $status === 410) {
                // 購読失効（Push サービス側で unsubscribe 済み）→ 即削除
                $this->repo->deleteById($sub['id']);
                $result['removed']++;
            } else {
                if ($this->repo->incrementFail($sub['id'])) {
                    $result['removed']++;
                } else {
                    $result['failed']++;
                }
            }
        }

        return $result;
    }

    /**
     * 1購読へ tickle（VAPID付き空POST）を送る。
     *
     * @param array{id:int, endpoint:string} $subscription
     * @return bool 2xx で送信できたら true（404/410 は購読削除、その他は fail_count++）
     */
    public function sendTickle(array $subscription): bool
    {
        $status = $this->postTickle($subscription['endpoint']);
        if ($status >= 200 && $status < 300) {
            $this->repo->markSent($subscription['id']);
            return true;
        }
        if ($status === 404 || $status === 410) {
            $this->repo->deleteById($subscription['id']);
        } else {
            $this->repo->incrementFail($subscription['id']);
        }
        return false;
    }

    /**
     * endpoint へ VAPID 付き空POSTを送り、HTTPステータスを返す（通信エラーは 0）。
     */
    private function postTickle(string $endpoint): int
    {
        $audience = $this->audienceOf($endpoint);
        if ($audience === '') {
            return 0;
        }

        try {
            $jwt = $this->buildVapidJwt($audience);
        } catch (\Throwable) {
            return 0; // 鍵不正など。送信不能（fail_count++ に倒す）
        }

        $ch = curl_init($endpoint);
        if ($ch === false) {
            return 0;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT_SEC,
            CURLOPT_HTTPHEADER => [
                'Authorization: vapid t=' . $jwt . ', k=' . SecretsConfig::$vapidPublicKey,
                'TTL: 3600',
                'Urgency: normal',
                'Content-Length: 0',
            ],
        ]);
        curl_exec($ch);
        // curl_close は PHP 8.5 で deprecated（GC で解放される）
        return (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    }

    /**
     * VAPID 用 ES256 JWT を生成する（audience ごとに1実行内キャッシュ）。
     *
     * header: {"typ":"JWT","alg":"ES256"}
     * claims: {"aud":<endpointの scheme://host>,"exp":now+12h,"sub":$vapidSubject}
     *
     * openssl_sign の ECDSA 署名は DER 形式なので、JOSE 用に raw r||s 64byte へ変換する。
     */
    public function buildVapidJwt(string $audience): string
    {
        if (isset($this->jwtCache[$audience])) {
            return $this->jwtCache[$audience];
        }

        $header = $this->base64url((string)json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = $this->base64url((string)json_encode([
            'aud' => $audience,
            'exp' => time() + self::JWT_TTL_SEC,
            'sub' => SecretsConfig::$vapidSubject,
        ], JSON_UNESCAPED_SLASHES));
        $signingInput = $header . '.' . $claims;

        $key = openssl_pkey_get_private(SecretsConfig::$vapidPrivateKey);
        if ($key === false) {
            throw new \RuntimeException('VAPID private key is invalid');
        }
        $derSignature = '';
        if (!openssl_sign($signingInput, $derSignature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('VAPID JWT signing failed');
        }

        $jwt = $signingInput . '.' . $this->base64url($this->derToRawSignature($derSignature));
        $this->jwtCache[$audience] = $jwt;
        return $jwt;
    }

    /** endpoint から audience（scheme://host[:port]）を取り出す。不正なら空文字。 */
    public function audienceOf(string $endpoint): string
    {
        $p = parse_url($endpoint);
        if (!is_array($p) || empty($p['scheme']) || empty($p['host'])) {
            return '';
        }
        $aud = $p['scheme'] . '://' . $p['host'];
        if (isset($p['port'])) {
            $aud .= ':' . $p['port'];
        }
        return $aud;
    }

    /**
     * DER 形式の ECDSA 署名（SEQUENCE{INTEGER r, INTEGER s}）を
     * JOSE 用の raw r||s（32byte + 32byte = 64byte）へ変換する。
     *
     * DER の INTEGER は可変長（先頭 0x00 パディングや 31byte 表現がある）ので、
     * 各値を左0詰めで 32byte に正規化する。
     */
    private function derToRawSignature(string $der): string
    {
        $pos = 0;
        $len = strlen($der);

        // SEQUENCE
        if ($len < 2 || ord($der[$pos++]) !== 0x30) {
            throw new \RuntimeException('Invalid DER signature: no SEQUENCE');
        }
        // SEQUENCE length（短形式 or 長形式 0x81）
        $seqLen = ord($der[$pos++]);
        if ($seqLen === 0x81) {
            $pos++;
        }

        $r = $this->readDerInteger($der, $pos);
        $s = $this->readDerInteger($der, $pos);

        return $this->padTo32($r) . $this->padTo32($s);
    }

    /** DER INTEGER を1つ読み取り、生バイト列（符号バイト除去済み）を返す。$pos は進む。 */
    private function readDerInteger(string $der, int &$pos): string
    {
        if ($pos + 2 > strlen($der) || ord($der[$pos]) !== 0x02) {
            throw new \RuntimeException('Invalid DER signature: no INTEGER');
        }
        $pos++;
        $intLen = ord($der[$pos++]);
        if ($intLen < 1 || $pos + $intLen > strlen($der)) {
            throw new \RuntimeException('Invalid DER signature: bad INTEGER length');
        }
        $bytes = substr($der, $pos, $intLen);
        $pos += $intLen;
        // 符号のための先頭 0x00 を除去
        return ltrim($bytes, "\x00");
    }

    /** 32byte に左0詰め（32byte 超は不正）。 */
    private function padTo32(string $bytes): string
    {
        if (strlen($bytes) > 32) {
            throw new \RuntimeException('Invalid DER signature: integer too long');
        }
        return str_pad($bytes, 32, "\x00", STR_PAD_LEFT);
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

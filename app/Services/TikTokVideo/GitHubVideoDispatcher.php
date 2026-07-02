<?php

declare(strict_types=1);

namespace App\Services\TikTokVideo;

use App\Config\AppConfig;
use App\Config\SecretsConfig;

/**
 * GitHub repository_dispatch イベントを送出して、TikTok 動画のレンダリング用
 * GitHub Actions ワークフロー（.github/workflows/tiktok-video.yml）を起動する。
 *
 * なぜ push 型か: 本番の公開 API は Cloudflare WAF が bot/データセンターIP の直叩きを block する
 * 設計（DB保護のため）で、GitHub Actions から pull させるには WAF に穴を開ける必要がある。
 * 本番 cron から GitHub へデータを送る方向なら WAF・CF 設定は一切触らずに済む。
 *
 * 認証: SecretsConfig::$gitHubVideoDispatchToken（fine-grained PAT・対象リポの
 * Contents: Read and write 権限のみ）。未設定なら何もしない（ログだけ残す）ので、
 * トークンを配らないローカル/stg/mock 環境ではこの機能は自然に無効になる。
 */
class GitHubVideoDispatcher
{
    private const API_TIMEOUT = 15;

    /**
     * @param string $eventType repository_dispatch の event_type（ワークフロー側の types と対）
     * @param array<string,mixed> $clientPayload client_payload（GitHub 上限: トップレベル10キー・約64KB）
     * @return bool 送出した場合 true / トークン未設定でスキップした場合 false
     * @throws \RuntimeException GitHub API がエラーを返した場合
     */
    public function dispatch(string $eventType, array $clientPayload): bool
    {
        $token = SecretsConfig::$gitHubVideoDispatchToken;
        if ($token === '') {
            return false;
        }

        $url = 'https://api.github.com/repos/' . AppConfig::TIKTOK_VIDEO_DISPATCH_REPO . '/dispatches';
        $body = json_encode([
            'event_type' => $eventType,
            'client_payload' => $clientPayload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::API_TIMEOUT,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'Authorization: Bearer ' . $token,
                'X-GitHub-Api-Version: 2022-11-28',
                'Content-Type: application/json',
                'User-Agent: openchat-review.me (tiktok-video-dispatch)',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // 成功は 204 No Content
        if ($response === false || $httpCode !== 204) {
            throw new \RuntimeException(
                "GitHub repository_dispatch 送出に失敗しました (HTTP {$httpCode}): "
                    . ($curlError ?: substr((string)$response, 0, 500))
            );
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\TikTokVideo;

/**
 * VOICEVOX エンジン（https://voicevox.hiroshiba.jp/ ・OSS）の HTTP API クライアント。
 *
 * ナレーション音声（ずんだもん等）の合成に使う。エンジンは GitHub Actions では
 * サービスコンテナ（voicevox/voicevox_engine:cpu-latest）、ローカルでは docker run で起動する。
 * 接続先は環境変数 VOICEVOX_URL（例 http://localhost:50021）。未設定ならナレーション無しで動く。
 *
 * 利用規約: 合成音声の商用利用は可・クレジット「VOICEVOX:ずんだもん」の表記が必須
 * （動画の締めスライドとキャプションに入れる。キャラクター規約は東北ずん子・ずんだもんプロジェクト）。
 */
class VoicevoxClient
{
    /** ずんだもん（ノーマル）の style id */
    public const SPEAKER_ZUNDAMON = 3;

    /** TikTok 向けに速めに読む（VOICEVOX 標準はやや遅く、ショート動画はテンポ優先） */
    private const SPEED_SCALE = 1.3;

    private const TIMEOUT = 60;

    public function __construct(
        private ?string $baseUrl = null,
    ) {
        $this->baseUrl = rtrim($this->baseUrl ?? (getenv('VOICEVOX_URL') ?: ''), '/');
    }

    /** エンジンが設定されているか（VOICEVOX_URL があるか）。呼び出し側のナレーション有効判定用 */
    public function isConfigured(): bool
    {
        return $this->baseUrl !== '';
    }

    /**
     * テキストを合成して WAV バイト列を返す（24kHz/16bit/mono）。
     *
     * @throws \RuntimeException エンジン未設定・API エラー
     */
    public function synthesize(string $text, int $speaker = self::SPEAKER_ZUNDAMON): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('VOICEVOX_URL が未設定です');
        }

        // 1) audio_query: テキスト → 合成パラメータ（JSON）
        $query = $this->request(
            '/audio_query?' . http_build_query(['text' => $text, 'speaker' => $speaker]),
            null,
        );
        $params = json_decode($query, true);
        if (!is_array($params)) {
            throw new \RuntimeException('VOICEVOX audio_query の応答が不正です');
        }
        $params['speedScale'] = self::SPEED_SCALE;

        // 2) synthesis: 合成パラメータ → WAV
        $wav = $this->request(
            '/synthesis?' . http_build_query(['speaker' => $speaker]),
            json_encode($params),
        );
        if (!str_starts_with($wav, 'RIFF')) {
            throw new \RuntimeException('VOICEVOX synthesis の応答が WAV ではありません');
        }
        return $wav;
    }

    /**
     * WAV バイト列の再生秒数（fmt チャンクの byteRate と data チャンク長から算出）。
     * スライドの表示時間をナレーション長に合わせるために使う。
     */
    public static function wavDuration(string $wav): float
    {
        if (strlen($wav) < 44 || !str_starts_with($wav, 'RIFF') || substr($wav, 8, 4) !== 'WAVE') {
            throw new \RuntimeException('WAV 形式ではありません');
        }
        $byteRate = null;
        $offset = 12;
        $len = strlen($wav);
        while ($offset + 8 <= $len) {
            $chunkId = substr($wav, $offset, 4);
            $chunkSize = unpack('V', substr($wav, $offset + 4, 4))[1];
            if ($chunkId === 'fmt ') {
                $byteRate = unpack('V', substr($wav, $offset + 16, 4))[1];
            } elseif ($chunkId === 'data' && $byteRate) {
                return $chunkSize / $byteRate;
            }
            $offset += 8 + $chunkSize + ($chunkSize % 2);
        }
        throw new \RuntimeException('WAV の data チャンクが見つかりません');
    }

    /** POST リクエスト（$body=null は空 POST）。2xx 以外は例外 */
    private function request(string $path, ?string $body): string
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body ?? '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            // ローカル/CI のサイドカーコンテナに繋ぐのでプロキシは通さない
            CURLOPT_PROXY => '',
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException(
                "VOICEVOX API エラー ({$path} HTTP {$httpCode}): " . ($curlError ?: substr((string)$response, 0, 300))
            );
        }
        return $response;
    }
}

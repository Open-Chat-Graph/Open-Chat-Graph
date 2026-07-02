<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\TikTokVideo\TikTokRisingVideoService;

/**
 * TikTok 用縦型動画をペイロード JSON からレンダリングする（GitHub Actions / ローカル用）。
 *
 * 使い方: php batch/exec/tiktok_video_render.php <payload.json> <出力ディレクトリ>
 *
 * DB には接続しない（必要なデータはペイロードに全部入っている）。ffmpeg 必須。
 * 本番 cron 用の BatchScriptLauncher（ロック・Discord通知）は使わない —
 * CI ではプロセス競合が無く、失敗は exit code でワークフローを落として可視化する。
 */

if (!isset($argv[1], $argv[2])) {
    fwrite(STDERR, "使い方: php batch/exec/tiktok_video_render.php <payload.json> <出力ディレクトリ>\n");
    exit(1);
}

[, $payloadPath, $outDir] = $argv;

$json = @file_get_contents($payloadPath);
if ($json === false) {
    fwrite(STDERR, "ペイロードを読み込めません: {$payloadPath}\n");
    exit(1);
}

$payload = json_decode($json, true);
if (!is_array($payload)) {
    fwrite(STDERR, "ペイロードの JSON が不正です: " . json_last_error_msg() . "\n");
    exit(1);
}

if (!is_dir($outDir) && !mkdir($outDir, 0777, true)) {
    fwrite(STDERR, "出力ディレクトリを作成できません: {$outDir}\n");
    exit(1);
}

try {
    /** @var TikTokRisingVideoService $service */
    $service = app(TikTokRisingVideoService::class);
    $result = $service->generate($payload, rtrim($outDir, '/'));
} catch (\Throwable $e) {
    fwrite(STDERR, '動画生成に失敗しました: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "video: {$result['video']}\n";
echo "caption: {$result['caption']}\n";
echo 'slides: ' . count($result['slides']) . "枚\n";

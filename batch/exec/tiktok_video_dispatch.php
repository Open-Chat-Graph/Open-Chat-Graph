<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Cron\Utility\BatchScriptLauncher;
use App\Services\Cron\Utility\CronUtility;
use App\Services\TikTokVideo\GitHubVideoDispatcher;
use App\Services\TikTokVideo\TikTokVideoPayloadBuilder;
use Shared\MimimalCmsConfig;

// TikTok 動画用のデイリー急上昇ランキングデータを GitHub repository_dispatch で送出する。
// 受け手は .github/workflows/tiktok-video.yml（GitHub Actions がレンダリング＆Discord通知）。
// トークン（SecretsConfig::$gitHubVideoDispatchToken）未設定の環境では何もしない。
set_time_limit(600);

if (isset($argv[1]) && $argv[1]) {
    MimimalCmsConfig::$urlRoot = $argv[1];
}

(new BatchScriptLauncher)->run(function () {
    /** @var TikTokVideoPayloadBuilder $builder */
    $builder = app(TikTokVideoPayloadBuilder::class);
    /** @var GitHubVideoDispatcher $dispatcher */
    $dispatcher = app(GitHubVideoDispatcher::class);

    $payload = $builder->build(5);
    if (!$payload['rooms']) {
        CronUtility::addCronLog('TikTok動画ディスパッチ: ランキングが空のためスキップ');
        return;
    }

    if ($dispatcher->dispatch('tiktok-video', $payload)) {
        CronUtility::addCronLog('TikTok動画ディスパッチ: GitHub Actions へ送出しました');
    } else {
        CronUtility::addVerboseCronLog('TikTok動画ディスパッチ: トークン未設定のためスキップ');
    }
});

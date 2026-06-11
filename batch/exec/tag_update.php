<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Admin\AdminTool;
use App\Services\Cron\Utility\CronUtility;
use App\Services\Recommend\RebuildAllRecommendTagsService;
use Shared\MimimalCmsConfig;

set_time_limit(3600 * 10);

if (isset($argv[1]) && $argv[1]) {
    MimimalCmsConfig::$urlRoot = $argv[1];
}

try {
    /**
     * @var RebuildAllRecommendTagsService $service
     */
    $service = app(RebuildAllRecommendTagsService::class);
    $service->handle();
} catch (\Throwable $e) {
    // サービス解決自体の失敗（デプロイ中のクラス入れ替わり等）に備えた最終防衛のみ
    CronUtility::addCronLog($e->__toString());
    AdminTool::sendDiscordNotify($e->__toString());
}

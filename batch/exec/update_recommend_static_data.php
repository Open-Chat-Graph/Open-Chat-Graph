<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Admin\AdminTool;
use App\Services\Cron\Utility\CronUtility;
use App\Services\Recommend\StaticData\UpdateRecommendStaticDataService;
use Shared\MimimalCmsConfig;

set_time_limit(3600 * 2);

if (isset($argv[1]) && $argv[1]) {
    MimimalCmsConfig::$urlRoot = $argv[1];
}

try {
    /**
     * @var UpdateRecommendStaticDataService $service
     */
    $service = app(UpdateRecommendStaticDataService::class);
    $service->handle();
} catch (\Throwable $e) {
    // サービス解決自体の失敗（デプロイ中のクラス入れ替わり等）に備えた最終防衛のみ
    CronUtility::addCronLog($e->__toString());
    AdminTool::sendDiscordNotify($e->__toString());
}

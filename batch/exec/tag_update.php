<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Admin\AdminTool;
use App\Services\Cron\Utility\CronUtility;
use App\Services\Recommend\RecommendUpdater;
use Shared\MimimalCmsConfig;

set_time_limit(3600 * 10);

try {
    if (isset($argv[1]) && $argv[1]) {
        MimimalCmsConfig::$urlRoot = $argv[1];
    }

    /**
     * @var RecommendUpdater $recommendUpdater
     */
    $recommendUpdater = app(RecommendUpdater::class);
    $now = date('Y-m-d H:i:s');
    AdminTool::sendDiscordNotify('updateRecommendTables start at ' . $now);
    $recommendUpdater->updateRecommendTables(false);
    AdminTool::sendDiscordNotify('updateRecommendTables done at ' . $now);
} catch (\Throwable $e) {
    CronUtility::addCronLog($e->__toString());
    AdminTool::sendDiscordNotify($e->__toString());
    AdminTool::sendDiscordNotify('updateRecommendTables failed ' . $now);
}

<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Admin\AdminTool;
use App\Services\Cron\Utility\CronUtility;
use App\Services\Recommend\RecommendUpdater;

set_time_limit(3600 * 10);

/**
 * @var RecommendUpdater $oc
 */
$oc = app(RecommendUpdater::class);


try {
    AdminTool::sendDiscordNotify('oc start');

    $oc->updateRecommendTables(false);

    AdminTool::sendDiscordNotify('oc done');
} catch (\Throwable $e) {
    CronUtility::addCronLog($e->__toString());
    AdminTool::sendDiscordNotify($e->__toString());
}

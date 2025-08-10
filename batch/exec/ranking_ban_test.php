<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Config\AppConfig;
use App\Services\Admin\AdminTool;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Services\RankingBan\RankingBanTableUpdater;

set_time_limit(60 * 60);

AppConfig::$isDevlopment = false;

try {
    /**
     * @var RankingBanTableUpdater $oc
     */
    $oc = app(RankingBanTableUpdater::class, ['time' => new \DateTime('2023-01-31 16:30:00')]);

    AdminTool::sendDiscordNotify('RankingBanTableUpdater start');

    $oc->updateRankingBanTable(OpenChatServicesUtility::getModifiedCronTime('now'));

    AdminTool::sendDiscordNotify('RankingBanTableUpdater done');
} catch (\Throwable $e) {
    addCronLog($e->__toString());
    AdminTool::sendDiscordNotify($e->__toString());
}

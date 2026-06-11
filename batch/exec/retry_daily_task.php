<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Admin\AdminTool;
use App\Services\Cron\SyncOpenChat;
use App\Services\Cron\Utility\CronUtility;
use Shared\MimimalCmsConfig;

try {
    if (isset($argv[1]) && $argv[1]) {
        MimimalCmsConfig::$urlRoot = $argv[1];
    }
    
    /**
     * @var SyncOpenChat $syncOpenChat
     */
    $syncOpenChat = app(SyncOpenChat::class);

    $syncOpenChat->handle(retryDailyTest: true);
    CronUtility::addCronLog('Done retry_daily_task');
} catch (\Throwable $e) {
    CronUtility::addCronLog($e->__toString());
    AdminTool::sendDiscordNotify($e->__toString());
}

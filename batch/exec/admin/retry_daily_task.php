<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Services\Cron\SyncOpenChat;
use App\Services\Cron\Utility\BatchScriptLauncher;
use App\Services\Cron\Utility\CronUtility;
use Shared\MimimalCmsConfig;

(new BatchScriptLauncher)->run(function () use ($argv) {
    if (isset($argv[1]) && $argv[1]) {
        MimimalCmsConfig::$urlRoot = $argv[1];
    }

    /**
     * @var SyncOpenChat $syncOpenChat
     */
    $syncOpenChat = app(SyncOpenChat::class);

    $syncOpenChat->handle(retryDailyTest: true);
    CronUtility::addCronLog('Done retry_daily_task');
});

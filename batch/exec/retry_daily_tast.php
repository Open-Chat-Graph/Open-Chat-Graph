<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Cron\SyncOpenChat;
use App\Services\Admin\AdminTool;
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
    addCronLog('End');
} catch (\Throwable $e) {
    addCronLog($e->__toString());
    AdminTool::sendDiscordNotify($e->__toString());
}

<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Config\AppConfig;
use App\ServiceProvider\ApiOpenChatDeleterServiceProvider;
use App\Services\Cron\SyncOpenChat;
use App\Services\Admin\AdminTool;
use Shared\MimimalCmsConfig;

try {
    if (isset($argv[1]) && $argv[1]) {
        MimimalCmsConfig::$urlRoot = $argv[1];
    }

    if (!MimimalCmsConfig::$urlRoot) {
        app(ApiOpenChatDeleterServiceProvider::class)->register();
    }

    /**
     * @var SyncOpenChat $syncOpenChat
     */
    $syncOpenChat = app(SyncOpenChat::class);

    $syncOpenChat->handleHalfHourCheck();
} catch (\Throwable $e) {
    AdminTool::sendDiscordNotify($e->__toString());
    addCronLog($e->__toString());
}

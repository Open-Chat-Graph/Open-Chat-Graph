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

    $syncOpenChat->handle(
        isset($argv[2]) && $argv[2] == 'dailyTest',
        isset($argv[3]) && $argv[3] == 'retryDailyTest'
    );
    addCronLog('End');

    if (!MimimalCmsConfig::$urlRoot) {
        set_time_limit(3600);

        // Create an instance of OcreviewApiDataImporter
        $importer = app(\App\Services\Cron\OcreviewApiDataImporter::class);

        // Execute the import process
        $importer->execute();
    }
} catch (\Throwable $e) {
    addCronLog($e->__toString());
    AdminTool::sendDiscordNotify($e->__toString());
}

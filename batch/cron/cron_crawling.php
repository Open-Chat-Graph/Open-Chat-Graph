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

        addCronLog('Start OcreviewApiDataImporter');
        // Execute the import process
        $importer->execute();
        addCronLog('End OcreviewApiDataImporter');
    }
} catch (\Throwable $e) {
    // killフラグによる強制終了の場合、開始から10時間以内ならDiscord通知しない
    $shouldNotify = true;
    if ($e instanceof \App\Exceptions\ApplicationException && $e->getCode() === AppConfig::DAILY_UPDATE_EXCEPTION_ERROR_CODE) {
        if (isDailyCronWithinHours(10)) {
            $shouldNotify = false;
            $elapsedHours = getDailyCronElapsedHours();
            addCronLog("killフラグによる強制終了（開始から" . round($elapsedHours, 2) . "時間経過）Discord通知スキップ");
        }
    }

    if ($shouldNotify) {
        AdminTool::sendDiscordNotify($e->__toString());
        addCronLog($e->__toString());
    }
}

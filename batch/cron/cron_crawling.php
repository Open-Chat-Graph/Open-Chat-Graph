<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Config\AppConfig;
use App\Exceptions\ApplicationException;
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

    if (!MimimalCmsConfig::$urlRoot) {
        set_time_limit(3600);

        // Create an instance of OcreviewApiDataImporter
        $importer = app(\App\Services\Cron\OcreviewApiDataImporter::class);

        addCronLog('アーカイブ用データベースにデータをインポート開始');
        // インポート処理を実行
        $importer->execute();
        addCronLog('アーカイブ用データベースにデータをインポート完了');
    }
} catch (\Throwable $e) {
    // killフラグによる強制終了の場合、開始から20時間以内ならDiscord通知しない
    $shouldNotify = true;
    if ($e instanceof ApplicationException && $e->getCode() === AppConfig::DAILY_UPDATE_EXCEPTION_ERROR_CODE) {
        if (isDailyCronWithinHours(20)) {
            $shouldNotify = false;
            $elapsedHours = getDailyCronElapsedHours();
            addCronLog("日次処理を中断（開始から" . round($elapsedHours, 2) . "時間経過）");
        }
    }

    if ($shouldNotify) {
        AdminTool::sendDiscordNotify($e->__toString());
        addCronLog($e->__toString());
    }
}

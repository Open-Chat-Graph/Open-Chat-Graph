<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Exceptions\ApplicationException;
use App\ServiceProvider\ApiOpenChatDeleterServiceProvider;
use App\Services\Admin\AdminTool;
use App\Services\Cron\SyncOpenChat;
use App\Services\Cron\Utility\CronUtility;
use ExceptionHandler\ExceptionHandler;
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
} catch (\Throwable $e) {
    // killフラグによる強制終了の場合、開始から20時間以内ならDiscord通知しない
    $shouldNotify = true;
    if ($e instanceof ApplicationException && $e->getCode() === ApplicationException::DAILY_UPDATE_EXCEPTION_ERROR_CODE) {
        if (isDailyCronWithinHours(20)) {
            $shouldNotify = false;
            $elapsedHours = getDailyCronElapsedHours();
            CronUtility::addCronLog("日次処理を中断（開始から" . round($elapsedHours, 2) . "時間経過）");
        }
    }


    if ($e instanceof ApplicationException && $e->getCode() === ApplicationException::RANKING_PERSISTENCE_TIMEOUT) {
        CronUtility::addCronLog("毎時処理を中断");
    }

    if ($shouldNotify) {
        AdminTool::sendDiscordNotify($e->__toString());
        CronUtility::addCronLog($e->getMessage());
        ExceptionHandler::errorLog($e);
    }
}

<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Exceptions\ApplicationException;
use App\ServiceProvider\ApiOpenChatDeleterServiceProvider;
use App\Services\Cron\SyncOpenChat;
use App\Services\Cron\Utility\BatchScriptLauncher;
use App\Services\Cron\Utility\ClassPreloader;
use App\Services\Cron\Utility\CronUtility;
use Shared\MimimalCmsConfig;

(new BatchScriptLauncher)->run(
    function () use ($argv) {
        if (isset($argv[1]) && $argv[1]) {
            MimimalCmsConfig::$urlRoot = $argv[1];
        }

        // 実行中にデプロイが重なっても新旧クラスが混在しないよう、開始時に全クラスを先読み
        ClassPreloader::preload();

        if (!MimimalCmsConfig::$urlRoot) {
            app(ApiOpenChatDeleterServiceProvider::class)->register();
        }

        app(SyncOpenChat::class)->handle(
            isset($argv[2]) && $argv[2] == 'dailyTest',
            isset($argv[3]) && $argv[3] == 'retryDailyTest'
        );
    },
    // killフラグによる強制終了かつ日次処理開始から20時間以内なら通知しない（中断ログのみ残す）
    function (\Throwable $e): bool {
        if (
            $e instanceof ApplicationException
            && $e->getCode() === ApplicationException::DAILY_UPDATE_EXCEPTION_ERROR_CODE
            && isDailyCronWithinHours(20)
        ) {
            $elapsedHours = getDailyCronElapsedHours();
            CronUtility::addCronLog("日次処理を中断（開始から" . round($elapsedHours, 2) . "時間経過）");
            return true;
        }

        return false;
    },
);

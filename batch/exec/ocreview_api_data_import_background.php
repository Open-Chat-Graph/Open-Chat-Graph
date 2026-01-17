<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Enum\SyncOpenChatStateType as StateType;
use App\Services\Cron\OcreviewApiDataImporter;
use App\Services\Cron\Utility\CronUtility;
use ExceptionHandler\ExceptionHandler;
use Shared\MimimalCmsConfig;

set_time_limit(3600 * 2);

try {
    MimimalCmsConfig::$urlRoot = '';

    /**
     * @var SyncOpenChatStateRepositoryInterface $state
     */
    $state = app(SyncOpenChatStateRepositoryInterface::class);

    // 二重実行チェック
    $bgState = $state->getArray(StateType::ocreviewApiDataImportBackground);
    $existingPid = $bgState['pid'] ?? null;

    if ($existingPid) {
        // 既存のプロセスが生きているか確認
        if (posix_getpgid((int)$existingPid) !== false) {
            CronUtility::addCronLog("既存のアーカイブ用DBインポートプロセス (PID: {$existingPid}) を強制終了します");
            exec("kill {$existingPid}");
            sleep(1); // プロセスが終了するまで少し待機
            CronUtility::addVerboseCronLog("新しいアーカイブ用DBインポートプロセスを開始します");
        } else {
            // プロセスが死んでいる場合は古い状態をクリア
            CronUtility::addVerboseCronLog("古いアーカイブ用DBインポートプロセス (PID: {$existingPid}) の状態をクリア");
        }
    }

    // PID、開始時刻を記録
    $state->setArray(StateType::ocreviewApiDataImportBackground, [
        'pid' => getmypid(),
        'startTime' => time(),
    ]);

    /**
     * @var OcreviewApiDataImporter $importer
     */
    $importer = app(OcreviewApiDataImporter::class);

    // アーカイブ用データベースにデータをインポート
    $importer->execute();

    // 正常終了：状態をクリア
    $state->setArray(StateType::ocreviewApiDataImportBackground, []);

    CronUtility::addVerboseCronLog('アーカイブ用DBインポートプロセスが正常終了しました');
} catch (\Throwable $e) {
    CronUtility::addCronLog($e->__toString());
    AdminTool::sendDiscordNotify($e->__toString());
    ExceptionHandler::errorLog($e);
}

<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Cron\Enum\SyncOpenChatStateType as StateType;
use App\Services\Cron\OcreviewApiDataImporter;
use App\Services\Cron\Utility\BatchScriptLauncher;
use App\Services\Cron\Utility\ClassPreloader;
use App\Services\Cron\Utility\CronUtility;
use Shared\MimimalCmsConfig;

set_time_limit(3600 * 2);

(new BatchScriptLauncher)->run(function () {
    MimimalCmsConfig::$urlRoot = '';

    // 実行中にデプロイが重なっても新旧クラスが混在しないよう、開始時に全クラスを先読み
    ClassPreloader::preload();

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
});

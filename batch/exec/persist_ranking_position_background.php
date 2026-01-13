<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Exceptions\ApplicationException;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Enum\SyncOpenChatStateType as StateType;
use App\Services\Cron\Utility\CronUtility;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Services\RankingPosition\Persistence\RankingPositionHourPersistence;
use ExceptionHandler\ExceptionHandler;
use Shared\MimimalCmsConfig;

set_time_limit(3600);

try {
    if (isset($argv[1]) && $argv[1]) {
        MimimalCmsConfig::$urlRoot = $argv[1];
    }

    $parentPid = isset($argv[2]) && $argv[2] ? (int)$argv[2] : null;

    $startTime = OpenChatServicesUtility::getModifiedCronTime('now')->format('Y-m-d H:i');

    /**
     * @var SyncOpenChatStateRepositoryInterface $state
     */
    $state = app(SyncOpenChatStateRepositoryInterface::class);

    // 二重実行チェック
    $bgState = $state->getArray(StateType::rankingPersistenceBackground);
    $existingPid = $bgState['pid'] ?? null;

    if ($existingPid) {
        // 既存のプロセスが生きているか確認
        if (posix_getpgid((int)$existingPid) !== false) {
            CronUtility::addCronLog("既存のバックグラウンドDB反映プロセス (PID: {$existingPid}) を強制終了します");
            CronUtility::killProcess($existingPid);
            CronUtility::addVerboseCronLog("新しいバックグラウンドプロセスを開始します");
        } else {
            // プロセスが死んでいる場合は古い状態をクリア
            CronUtility::addVerboseCronLog("古いバックグラウンドプロセス (PID: {$existingPid}) の状態をクリア");
        }
    }

    // PID、親PID、開始時刻を記録
    $state->setArray(StateType::rankingPersistenceBackground, [
        'pid' => getmypid(),
        'parentPid' => $parentPid,
        'startTime' => time(),
    ]);

    /**
     * @var RankingPositionHourPersistence $persistence
     */
    $persistence = app(RankingPositionHourPersistence::class);

    // 全カテゴリのDB反映処理を実行
    $persistence->persistAllCategoriesBackground();

    // 正常終了：状態をクリア
    $state->setArray(StateType::rankingPersistenceBackground, []);

    CronUtility::addVerboseCronLog('バックグラウンドDB反映プロセスが正常終了しました');
} catch (\Throwable $e) {
    // killフラグによる強制終了の場合、開始から20時間以内ならDiscord通知しない
    $shouldNotify = true;
    if ($e instanceof ApplicationException && $e->getCode() === ApplicationException::RANKING_PERSISTENCE_TIMEOUT) {
        $shouldNotify = false;
        CronUtility::addCronLog("【毎時処理】親プロセスがタイムアウト指示を受け取り終了しました。時間帯: {$startTime}");
    }

    if ($shouldNotify) {
        ExceptionHandler::errorLog($e);
        $message = CronUtility::addCronLog($e->getMessage());
        AdminTool::sendDiscordNotify($message);
    }
}

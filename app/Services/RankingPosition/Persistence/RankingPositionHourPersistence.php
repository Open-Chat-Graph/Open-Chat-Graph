<?php

declare(strict_types=1);

namespace App\Services\RankingPosition\Persistence;

use App\Config\AppConfig;
use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Enum\SyncOpenChatStateType;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use Shared\MimimalCmsConfig;

/**
 * 毎時ランキングデータのDB反映処理
 *
 * ダウンロード処理と並列実行するため、バックグラウンドプロセスで動作する。
 * ストレージファイルを監視し、ファイルが準備でき次第、順次DB反映を行う。
 *
 * 処理フロー:
 * 1. メインプロセス: startBackgroundPersistence() でバックグラウンドプロセスを起動
 * 2. メインプロセス: ランキングダウンロード処理を実行（並列）
 * 3. バックグラウンド: persistAllCategoriesBackground() でストレージ監視＆DB反映
 * 4. メインプロセス: waitForBackgroundCompletion() でバックグラウンド完了を待機
 */
class RankingPositionHourPersistence
{
    /** バックグラウンドプロセスの最大待機時間（秒） */
    private const MAX_WAIT_SECONDS = 2400; // 最大40分待機

    /** ストレージファイル待機中のログ出力間隔（ループ回数）
     * whileループは約1秒ごとに実行されるため、60回 ≒ 60秒 */
    private const LOG_INTERVAL_LOOP_COUNT = 60;

    function __construct(
        private RankingPositionHourPersistenceProcess $process,
        private RankingPositionHourRepositoryInterface $rankingPositionHourRepository,
        private SyncOpenChatStateRepositoryInterface $state
    ) {}


    /**
     * バックグラウンドプロセスを起動
     *
     * persist_ranking_position_background.php を別プロセスとして起動し、
     * DB反映処理をバックグラウンドで実行する。これにより、メインプロセスは
     * ランキングダウンロード処理を並行して実行できる。
     */
    function startBackgroundPersistence(): void
    {
        $parentPid = getmypid();
        $arg = escapeshellarg(\Shared\MimimalCmsConfig::$urlRoot);
        $parentPidArg = escapeshellarg((string)$parentPid);
        $path = AppConfig::ROOT_PATH . 'batch/exec/persist_ranking_position_background.php';
        exec(PHP_BINARY . " {$path} {$arg} {$parentPidArg} >/dev/null 2>&1 &");
        addVerboseCronLog('毎時ランキングDB反映をバックグラウンドで開始');
    }

    /**
     * バックグラウンドプロセスの完了を待つ
     *
     * PID監視により、バックグラウンドプロセスの状態を確認する。
     * - PIDがクリアされた: 正常終了
     * - プロセスが死んでいる: 異常終了
     * - タイムアウト: 処理時間超過
     *
     * @throws \RuntimeException バックグラウンドプロセスが異常終了またはタイムアウトした場合
     */
    function waitForBackgroundCompletion(): void
    {
        addVerboseCronLog('バックグラウンドDB反映の完了を待機中');

        while (true) {
            // バックグラウンドプロセスの状態を取得
            $bgState = $this->state->getArray(SyncOpenChatStateType::rankingPersistenceBackground);
            $pid = $bgState['pid'] ?? null;
            $startTime = $bgState['startTime'] ?? null;

            // PIDがクリアされていたら正常終了
            if (!$pid) {
                addVerboseCronLog('バックグラウンドDB反映完了を確認');
                return;
            }

            // posix_getpgid()でプロセスの生存確認（falseならプロセスが死んでいる）
            if (posix_getpgid((int)$pid) === false) {
                throw new \RuntimeException("バックグラウンドDB反映プロセスが異常終了 (PID: {$pid})");
            }

            // 開始時刻からの経過時間をチェック
            if ($startTime && time() - (int)$startTime > self::MAX_WAIT_SECONDS) {
                exec("kill {$pid}");
                throw new \RuntimeException("バックグラウンドDB反映の待機タイムアウト（40分） (PID: {$pid})");
            }

            sleep(2);
        }
    }

    /**
     * バックグラウンドでの全カテゴリDB反映処理
     *
     * ストレージファイルを監視し、ファイルが準備でき次第、順次DB反映を行う。
     * ダウンロード処理と並行して動作するため、全ファイルが揃うのを待たずに処理できる。
     *
     * 処理フロー:
     * 1. 全カテゴリ×2種類（急上昇/ランキング）の処理状態を初期化
     * 2. whileループでストレージファイルを監視
     * 3. ファイルのタイムスタンプが期待値と一致したらDB反映
     * 4. 全カテゴリ完了後、集計処理と古いデータ削除を実行
     *
     * @throws \RuntimeException タイムアウトの場合
     */
    function persistAllCategoriesBackground(): void
    {
        // OpenChatデータのキャッシュを初期化（emid→id変換用）
        $this->process->initializeCache();

        // 現在時間をcron毎時実行時間に調整した値
        $expectedFileTime = OpenChatServicesUtility::getModifiedCronTime('now')->format('Y-m-d H:i:s');
        $expectedFileTimeLog = (new \DateTime($expectedFileTime))->format('Y-m-d H:i');
        addVerboseCronLog("毎時ランキングをデータベースに反映するバックグラウンド処理を開始（対象時刻: " . $expectedFileTimeLog . "）");

        // ストレージファイル監視ループ（ファイルが準備でき次第、順次処理）
        $startTime = time();
        $loopCount = 0;

        while (true) {
            $loopCount++;

            // 1サイクル分の処理を実行
            if ($this->process->processOneCycle($expectedFileTime)) {
                break; // 全カテゴリ完了
            }

            // 定期的に待機中のログを出力（60ループごと ≒ 60秒ごと）
            if ($loopCount % self::LOG_INTERVAL_LOOP_COUNT === 0) {
                addVerboseCronLog(
                    'ストレージファイル待機中: ' . (time() - $startTime) . '秒経過、残り'
                        . count(array_filter($this->process->getProcessedState(), fn($p) => !$p['rising'] || !$p['ranking']))
                        . 'カテゴリ（バックグラウンド）'
                );
            }

            // タイムアウトチェック（40分経過したらエラー）
            if (time() - $startTime > self::MAX_WAIT_SECONDS) {
                $this->handleTimeoutAndRestartCron();
            }

            sleep(1); // CPU負荷軽減のため1秒待機してから再チェック
        }

        // 最終処理：全体集計と古いデータ削除
        addVerboseCronLog('ランキング掲載総数をデータベースに反映中（バックグラウンド）');
        $this->rankingPositionHourRepository->insertTotalCount($expectedFileTime);
        addCronLog("毎時ランキング全データをデータベースに反映完了（" . $expectedFileTimeLog . "）");


        // 古いデータを削除（1日前より古いデータ）
        $deleteTime = new \DateTime($expectedFileTime);
        $deleteTime->modify('- 1day');
        $this->rankingPositionHourRepository->delete($deleteTime);
        addCronLog('古いランキングデータを削除完了（' .  $deleteTime->format('Y-m-d H:i') . " 以前）");

        // キャッシュをクリア
        $this->process->afterClearCache();
    }

    /**
     * タイムアウト処理を実行して親プロセスをkill、cron_crawling.phpを再実行
     *
     * @throws \RuntimeException
     */
    private function handleTimeoutAndRestartCron(): void
    {
        $message = "毎時ランキングDB反映バックグラウンド: タイムアウト（" . self::MAX_WAIT_SECONDS . "秒）";
        addCronLog($message);
        AdminTool::sendDiscordNotify($message);

        // 親プロセスをkillしてcron_crawling.phpを再実行
        $bgState = $this->state->getArray(SyncOpenChatStateType::rankingPersistenceBackground);
        $parentPid = $bgState['parentPid'] ?? null;

        if ($parentPid) {
            addCronLog("親プロセス (PID: {$parentPid}) をkillします");
            exec("kill {$parentPid}");
            sleep(1);

            // cron_crawling.phpを再実行
            $arg = escapeshellarg(MimimalCmsConfig::$urlRoot);
            $path = AppConfig::ROOT_PATH . 'batch/cron/cron_crawling.php';
            exec(PHP_BINARY . " {$path} {$arg} >/dev/null 2>&1 &");
            addCronLog('cron_crawling.phpを再実行しました');
        }

        throw new \RuntimeException($message);
    }
}

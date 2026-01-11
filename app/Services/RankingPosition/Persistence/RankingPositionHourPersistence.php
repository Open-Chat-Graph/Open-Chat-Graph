<?php

declare(strict_types=1);

namespace App\Services\RankingPosition\Persistence;

use App\Config\AppConfig;
use App\Models\Repositories\OpenChatDataForUpdaterWithCacheRepositoryInterface;
use App\Models\Repositories\RankingPosition\Dto\RankingPositionHourInsertDto;
use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Enum\SyncOpenChatStateType;
use App\Services\OpenChat\Enum\RankingType;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Services\RankingPosition\Store\RankingPositionStore;
use App\Services\RankingPosition\Store\RisingPositionStore;
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
        private OpenChatDataForUpdaterWithCacheRepositoryInterface $openChatDataWithCache,
        private RankingPositionHourRepositoryInterface $rankingPositionHourRepository,
        private RisingPositionStore $risingPositionStore,
        private RankingPositionStore $rankingPositionStore,
        private SyncOpenChatStateRepositoryInterface $state
    ) {}

    /**
     * 経過時間を分秒形式でフォーマット
     */
    private function formatElapsedTime(float $startTime): string
    {
        $elapsedSeconds = microtime(true) - $startTime;
        $minutes = (int) floor($elapsedSeconds / 60);
        $seconds = (int) round($elapsedSeconds - ($minutes * 60));
        return $minutes > 0 ? "{$minutes}分{$seconds}秒" : "{$seconds}秒";
    }

    /**
     * ログ用のカテゴリラベルを生成
     */
    private function getCategoryLabelWithCount(string $categoryName, string $typeLabel, ?int $count = null): string
    {
        return "{$categoryName}の{$typeLabel}" . (is_null($count) ? "" : " {$count}件");
    }

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
        $this->openChatDataWithCache->clearCache();
        $this->openChatDataWithCache->cacheOpenChatData(true);

        // cron実行時刻を毎時0分に調整した期待値（例: "2024-01-15 12:00:00"）
        $expectedFileTime = OpenChatServicesUtility::getModifiedCronTime('now')->format('Y-m-d H:i:s');
        addVerboseCronLog("毎時ランキングDB反映バックグラウンド開始（対象時刻: {$expectedFileTime}）");

        // 各カテゴリの処理状態を追跡（未処理: false、処理済み: true）
        // 例: [0 => ['rising' => false, 'ranking' => false], 1 => [...], ...]
        $categories = AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot];
        $processed = array_fill_keys(
            array_values($categories),
            ['rising' => false, 'ranking' => false]
        );

        // 処理対象の定義（急上昇とランキングの2種類）
        $processTargets = [
            ['type' => RankingType::Rising, 'store' => $this->risingPositionStore, 'key' => 'rising'],
            ['type' => RankingType::Ranking, 'store' => $this->rankingPositionStore, 'key' => 'ranking'],
        ];

        // ストレージファイル監視ループ（ファイルが準備でき次第、順次処理）
        $startTime = time();

        $loopCount = 0;
        while (true) {
            $allCompleted = true;
            $loopCount++;

            // 全カテゴリ×2種類（急上昇/ランキング）をチェック
            foreach ($categories as $key => $category) {
                foreach ($processTargets as $target) {
                    // 既に処理済みならスキップ
                    if ($processed[$category][$target['key']]) {
                        continue;
                    }

                    // ファイルが準備できていたら処理、まだならfalseが返る
                    if (!$this->processCategoryTarget($category, $key, $target, $expectedFileTime, $processed)) {
                        $allCompleted = false;
                    }
                }
            }

            // 全カテゴリ×2種類の処理が完了したらループを抜ける
            if ($allCompleted) {
                break;
            }

            // 定期的に待機中のログを出力（60ループごと ≒ 60秒ごと）
            if ($loopCount % self::LOG_INTERVAL_LOOP_COUNT === 0) {
                $elapsed = time() - $startTime;
                $remaining = count(array_filter($processed, fn($p) => !$p['rising'] || !$p['ranking']));
                addVerboseCronLog("ストレージファイル待機中: {$elapsed}秒経過、残り{$remaining}カテゴリ");
            }

            // タイムアウトチェック（40分経過したらエラー）
            if (time() - $startTime > self::MAX_WAIT_SECONDS) {
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

            // CPU負荷軽減のため1秒待機してから再チェック
            sleep(1);
        }

        // 最終処理：全体集計と古いデータ削除
        addVerboseCronLog('全カテゴリのDB反映完了、最終処理を実行（バックグラウンド）');
        $this->rankingPositionHourRepository->insertTotalCount($expectedFileTime);
        addCronLog("毎時ランキング全データをデータベースに反映完了（{$expectedFileTime}）");

        // 1日前より古いデータを削除
        $deleteTime = new \DateTime($expectedFileTime);
        $deleteTime->modify('- 1day');
        $this->rankingPositionHourRepository->delete($deleteTime);

        $deleteTimeStr = $deleteTime->format('Y-m-d H:i:s');
        addCronLog("古いランキングデータを削除（{$deleteTimeStr}以前）");

        $this->openChatDataWithCache->clearCache();
    }

    /**
     * カテゴリとターゲット（Rising/Ranking）の組み合わせを処理
     *
     * ストレージファイルのタイムスタンプをチェックし、期待値と一致していれば
     * DB反映処理を実行する。まだファイルが準備できていない場合はfalseを返す。
     *
     * @param int $category カテゴリID
     * @param string $categoryName カテゴリ名（ログ用）
     * @param array $target 処理対象情報（type, store, key）
     * @param string $expectedFileTime 期待されるファイルタイムスタンプ
     * @param array $processed 処理状態の配列（参照渡し）
     * @return bool 処理が完了した場合true、まだファイルが準備できていない場合false
     */
    private function processCategoryTarget(int $category, string $categoryName, array $target, string $expectedFileTime, array &$processed): bool
    {
        $categoryStr = (string)$category;

        // ストレージファイルのタイムスタンプをチェック
        try {
            $fileTime = $target['store']->getFileDateTime($categoryStr)->format('Y-m-d H:i:s');
            if ($fileTime !== $expectedFileTime) {
                return false; // タイムスタンプが一致しない（まだダウンロード中）
            }
        } catch (\Throwable) {
            return false; // ファイルがまだ存在しない
        }

        // DB反映処理を実行
        $typeLabel = $target['type'] === RankingType::Rising ? '急上昇' : 'ランキング';
        $label = $this->getCategoryLabelWithCount($categoryName, $typeLabel);
        $perfStartTime = microtime(true);
        addVerboseCronLog("{$label}をデータベースに反映中");

        // ストレージからデータを取得してDTO配列に変換
        [, $ocDtoArray] = $target['store']->getStorageData($categoryStr);
        $insertDtoArray = $this->createInsertDtoArray($ocDtoArray);
        unset($ocDtoArray); // メモリ解放

        // ランキングデータをDBに挿入
        $this->rankingPositionHourRepository->insertFromDtoArray($target['type'], $expectedFileTime, $insertDtoArray);

        // ランキングタイプまたは全体カテゴリ（0）の場合、メンバー数も記録
        if ($target['type'] === RankingType::Ranking || (int)$category === 0) {
            $this->rankingPositionHourRepository->insertHourMemberFromDtoArray($expectedFileTime, $insertDtoArray);
        }

        addVerboseCronLog("{$label}をデータベースに反映完了（{$this->formatElapsedTime($perfStartTime)}）", count($insertDtoArray));
        unset($insertDtoArray); // メモリ解放

        // 処理完了フラグを立てる
        $processed[$category][$target['key']] = true;
        return true;
    }

    /**
     * OpenChatDto配列をRankingPositionHourInsertDto配列に変換
     *
     * emidからopenChatIdを取得し、IDが見つからないものは除外する。
     *
     * @param OpenChatDto[] $data ストレージから取得したランキングデータ
     * @return RankingPositionHourInsertDto[] DB挿入用DTO配列
     */
    private function createInsertDtoArray(array $data): array
    {
        return array_values(array_filter(array_map(
            fn($dto, $key) => ($id = $this->openChatDataWithCache->getOpenChatIdByEmid($dto->emid))
                ? new RankingPositionHourInsertDto($id, $key + 1, $dto->category ?? 0, $dto->memberCount)
                : null, // IDが見つからない場合はnull（後でfilterで除外）
            $data,
            array_keys($data)
        )));
    }
}

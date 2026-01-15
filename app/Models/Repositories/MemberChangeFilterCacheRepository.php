<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Config\AppConfig;
use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Cron\Enum\SyncOpenChatStateType;
use App\Services\Storage\FileStorageInterface;

/**
 * メンバー数変動フィルターキャッシュのリポジトリ
 *
 * 統計データから「メンバー数が変動している部屋」を抽出した結果をキャッシュし、
 * 日次処理の中断・再開時に重複クエリを防止する
 *
 * ## 3つのデータ（個別にキャッシュ）
 * 1. 変動がある部屋（過去8日間でメンバー数変動）→ filterMemberChange
 * 2. レコード数が8以下の部屋（新規部屋）        → filterNewRooms
 * 3. 最後のレコードが1週間以上前の部屋（週次更新）→ filterWeeklyUpdate
 *
 * ## 使い分け
 * - hourly: 1（キャッシュ） + 2（リアルタイム） → getForHourly()
 * - daily:  1 + 2 + 3（全部キャッシュ）       → getForDaily()
 */
class MemberChangeFilterCacheRepository implements MemberChangeFilterCacheRepositoryInterface
{
    function __construct(
        private StatisticsRepositoryInterface $statisticsRepository,
        private SyncOpenChatStateRepositoryInterface $syncStateRepository,
        private FileStorageInterface $fileStorage,
    ) {}

    /**
     * 毎時処理用: 変動がある部屋（キャッシュ） + 新規部屋（リアルタイム）
     *
     * @param string $date 日付（Y-m-d形式）
     * @return int[] オープンチャットIDの配列
     */
    function getForHourly(string $date): array
    {
        // 1. 変動がある部屋（キャッシュ）
        $memberChange = $this->getMemberChange($date);

        // 2. 新規部屋（リアルタイム取得し、キャッシュも更新）
        $newRooms = $this->statisticsRepository->getNewRoomsWithLessThan8Records();
        $this->saveCache('filterNewRooms', $date, $newRooms);

        return array_unique(array_merge($memberChange, $newRooms));
    }

    /**
     * 日次処理用: 変動がある部屋 + 新規部屋 + 週次更新部屋（全部キャッシュ）
     *
     * 2回目以降は全てキャッシュから取得（新規部屋も含む）
     *
     * @param string $date 日付（Y-m-d形式）
     * @return int[] オープンチャットIDの配列
     */
    function getForDaily(string $date): array
    {
        // 1. 変動がある部屋（キャッシュ）
        $memberChange = $this->getMemberChange($date);

        // 2. 新規部屋（キャッシュ）
        $newRooms = $this->getNewRooms($date);

        // 3. 週次更新部屋（キャッシュ）
        $weeklyUpdate = $this->getWeeklyUpdate($date);

        return array_unique(array_merge($memberChange, $newRooms, $weeklyUpdate));
    }

    /**
     * 1. 変動がある部屋を取得（キャッシュ優先）
     */
    private function getMemberChange(string $date): array
    {
        $cached = $this->loadCache('filterMemberChange', $date);
        if ($cached !== null) {
            return $cached;
        }

        $data = $this->statisticsRepository->getMemberChangeWithinLastWeek($date);
        $this->saveCache('filterMemberChange', $date, $data);

        return $data;
    }

    /**
     * 2. 新規部屋を取得（キャッシュ優先、daily用）
     */
    private function getNewRooms(string $date): array
    {
        $cached = $this->loadCache('filterNewRooms', $date);
        if ($cached !== null) {
            return $cached;
        }

        $data = $this->statisticsRepository->getNewRoomsWithLessThan8Records();
        $this->saveCache('filterNewRooms', $date, $data);

        return $data;
    }

    /**
     * 3. 週次更新部屋を取得（キャッシュ優先）
     */
    private function getWeeklyUpdate(string $date): array
    {
        $cached = $this->loadCache('filterWeeklyUpdate', $date);
        if ($cached !== null) {
            return $cached;
        }

        $data = $this->statisticsRepository->getWeeklyUpdateRooms($date);
        $this->saveCache('filterWeeklyUpdate', $date, $data);

        return $data;
    }

    /**
     * キャッシュから読み込む
     */
    private function loadCache(string $key, string $date): ?array
    {
        $path = \App\Services\Storage\FileStorageService::getStorageFilePath($key);

        if (!file_exists($path)) {
            return null;
        }

        // 日付チェック（DBから取得）
        $cachedDate = $this->syncStateRepository->getString(SyncOpenChatStateType::filterCacheDate);
        if ($cachedDate !== $date) {
            return null;
        }

        $cached = $this->fileStorage->getSerializedFile('@' . $key);
        return $cached === false ? null : $cached;
    }

    /**
     * キャッシュに保存
     */
    private function saveCache(string $key, string $date, array $data): void
    {
        $this->fileStorage->saveSerializedFile('@' . $key, $data);

        // 日付も更新（DBに保存）
        $this->syncStateRepository->setString(SyncOpenChatStateType::filterCacheDate, $date);
    }
}

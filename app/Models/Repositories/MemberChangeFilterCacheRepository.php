<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;
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
     *
     * キャッシュファイル内に日付を埋め込み、キーごとに独立して日付検証を行う。
     * 共有の filterCacheDate を使うと、毎時タスクが filterMemberChange/filterNewRooms の
     * キャッシュ保存時に日付を更新してしまい、filterWeeklyUpdate のキャッシュが
     * 古いまま有効と誤判定されるバグがあった。
     */
    private function loadCache(string $key, string $date): ?array
    {
        $path = $this->fileStorage->getStorageFilePath($key);

        if (!file_exists($path)) {
            return null;
        }

        $cached = $this->fileStorage->getSerializedFile('@' . $key);
        if ($cached === false) {
            return null;
        }

        // 新形式: 日付がファイル内に埋め込まれている
        if (is_array($cached) && array_key_exists('_cacheDate', $cached) && array_key_exists('_cacheData', $cached)) {
            if ($cached['_cacheDate'] !== $date) {
                return null;
            }
            return $cached['_cacheData'];
        }

        // 旧形式: 日付が埋め込まれていない → キャッシュ無効として再取得
        return null;
    }

    /**
     * キャッシュに保存（日付をファイル内に埋め込む）
     */
    private function saveCache(string $key, string $date, array $data): void
    {
        $this->fileStorage->saveSerializedFile('@' . $key, [
            '_cacheDate' => $date,
            '_cacheData' => $data,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Models\SQLite\SQLiteOcgraphSqlapi;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;

class AllRoomStatsRepository implements AllRoomStatsRepositoryInterface
{
    private const BAND_LABELS = [
        1 => '1~10人',
        2 => '11~20人',
        3 => '21~50人',
        4 => '51~100人',
        5 => '101~200人',
        6 => '201~500人',
        7 => '501~1000人',
        8 => '1001人以上',
    ];

    // --- 基本統計 ---

    /**
     * 現在登録中の総ルーム数を取得
     */
    public function getTotalRoomCount(): int
    {
        return (int) DB::execute(
            'SELECT COUNT(*) FROM open_chat'
        )->fetchColumn();
    }

    /**
     * 現在登録中の全ルームの合計メンバー数を取得
     */
    public function getTotalMemberCount(): int
    {
        return (int) DB::execute(
            'SELECT SUM(member) FROM open_chat'
        )->fetchColumn();
    }

    /**
     * 最も古いルームの登録日時を取得（データなしの場合はnull）
     */
    public function getTrackingStartDate(): ?string
    {
        $result = DB::execute(
            'SELECT MIN(created_at) FROM open_chat'
        )->fetchColumn();

        return $result !== false ? (string) $result : null;
    }

    // --- 期間別集計 ---

    /**
     * 指定期間内に新規登録されたルーム数を取得
     *
     * @param string $interval MySQL INTERVAL形式（例: '1 hour', '7 day', '1 month'）
     */
    public function getNewRoomCountSince(string $interval): int
    {
        return (int) DB::execute(
            "SELECT COUNT(*) FROM open_chat WHERE created_at >= NOW() - INTERVAL {$interval}"
        )->fetchColumn();
    }

    // --- 時系列比較 ---

    /**
     * メンバー増減の内訳を4分類で取得
     *
     * - increased: 現存ルームのうち増加したルームの合計（>= 0）
     * - decreased: 現存ルームのうち減少したルームの合計（<= 0）
     * - lost: 消滅ルーム（過去にあったが今日にない）の過去メンバー合計（<= 0）
     * - gained: 新規ルーム（今日にあるが過去にない）の現在メンバー合計（>= 0）
     *
     * 純増数 = increased + decreased + lost + gained
     *
     * @param string $modifier SQLite date modifier形式（例: '-1 month'）
     * @return array{increased: int, decreased: int, lost: int, gained: int}
     */
    public function getMemberTrendBreakdown(string $modifier): array
    {
        $today = OpenChatServicesUtility::getCronModifiedStatsMemberDate();

        SQLiteOcgraphSqlapi::connect(['mode' => '?mode=ro']);

        // 現存ルーム（両方の日付にデータあり）の増減
        $existing = SQLiteOcgraphSqlapi::execute(
            "SELECT
                COALESCE(SUM(CASE WHEN diff > 0 THEN diff END), 0) AS increased,
                COALESCE(SUM(CASE WHEN diff < 0 THEN diff END), 0) AS decreased
            FROM (
                SELECT today.member_count - past.member_count AS diff
                FROM daily_member_statistics today
                JOIN daily_member_statistics past
                    ON today.openchat_id = past.openchat_id
                WHERE today.statistics_date = :today
                  AND past.statistics_date = date(:today2, :modifier)
            ) sub",
            ['today' => $today, 'today2' => $today, 'modifier' => $modifier]
        )->fetch(\PDO::FETCH_ASSOC);

        // 消滅ルーム（過去にあるが今日にない）の過去メンバー合計
        $lost = (int) SQLiteOcgraphSqlapi::fetchColumn(
            "SELECT -COALESCE(SUM(member_count), 0)
            FROM daily_member_statistics
            WHERE statistics_date = date(:today, :modifier)
              AND openchat_id NOT IN (
                SELECT openchat_id FROM daily_member_statistics WHERE statistics_date = :today2
              )",
            ['today' => $today, 'modifier' => $modifier, 'today2' => $today]
        );

        // 新規ルーム（今日にあるが過去にない）の現在メンバー合計
        $gained = (int) SQLiteOcgraphSqlapi::fetchColumn(
            "SELECT COALESCE(SUM(member_count), 0)
            FROM daily_member_statistics
            WHERE statistics_date = :today
              AND openchat_id NOT IN (
                SELECT openchat_id FROM daily_member_statistics WHERE statistics_date = date(:today2, :modifier)
              )",
            ['today' => $today, 'today2' => $today, 'modifier' => $modifier]
        );

        SQLiteOcgraphSqlapi::$pdo = null;

        return [
            'increased' => (int) ($existing['increased'] ?? 0),
            'decreased' => (int) ($existing['decreased'] ?? 0),
            'lost' => $lost,
            'gained' => $gained,
        ];
    }

    /**
     * 消滅ルーム（過去にあるが今日にない）を閉鎖/掲載終了に分割して取得
     *
     * SQLiteのdaily_member_statisticsとopen_chat_deletedを使い、
     * 消滅ルーム全体を「閉鎖（open_chat_deletedに存在）」と「掲載終了（存在しない）」に分割する。
     *
     * @param string $modifier SQLite date modifier形式（例: '-1 month'）
     * @return array{closed_rooms: int, closed_members: int, delisted_rooms: int, delisted_members: int}
     */
    public function getDisappearedRoomBreakdown(string $modifier): array
    {
        $today = OpenChatServicesUtility::getCronModifiedStatsMemberDate();
        $pastDate = date('Y-m-d', strtotime($modifier, strtotime($today)));

        SQLiteOcgraphSqlapi::connect(['mode' => '?mode=ro']);

        $result = SQLiteOcgraphSqlapi::execute(
            "SELECT
                SUM(CASE WHEN ocd.id IS NOT NULL THEN 1 ELSE 0 END) AS closed_rooms,
                SUM(CASE WHEN ocd.id IS NOT NULL THEN past.member_count ELSE 0 END) AS closed_members,
                SUM(CASE WHEN ocd.id IS NULL THEN 1 ELSE 0 END) AS delisted_rooms,
                SUM(CASE WHEN ocd.id IS NULL THEN past.member_count ELSE 0 END) AS delisted_members
            FROM daily_member_statistics past
            LEFT JOIN open_chat_deleted ocd
                ON past.openchat_id = ocd.id AND ocd.deleted_at >= :past_date
            WHERE past.statistics_date = date(:today, :modifier)
              AND past.openchat_id NOT IN (
                SELECT openchat_id FROM daily_member_statistics WHERE statistics_date = :today2
              )",
            ['today' => $today, 'modifier' => $modifier, 'today2' => $today, 'past_date' => $pastDate]
        )->fetch(\PDO::FETCH_ASSOC);

        SQLiteOcgraphSqlapi::$pdo = null;

        return [
            'closed_rooms' => (int) ($result['closed_rooms'] ?? 0),
            'closed_members' => (int) ($result['closed_members'] ?? 0),
            'delisted_rooms' => (int) ($result['delisted_rooms'] ?? 0),
            'delisted_members' => (int) ($result['delisted_members'] ?? 0),
        ];
    }

    // --- 分布・カテゴリー ---

    /**
     * 参加者数の分布を8段階の人数帯で取得（MySQL open_chat テーブルから）
     *
     * @return array{ band_id: int, band_label: string, room_count: int, total_members: int }[]
     */
    public function getMemberDistribution(): array
    {
        $rows = DB::fetchAll(
            "SELECT
                CASE
                    WHEN member <= 10 THEN 1
                    WHEN member <= 20 THEN 2
                    WHEN member <= 50 THEN 3
                    WHEN member <= 100 THEN 4
                    WHEN member <= 200 THEN 5
                    WHEN member <= 500 THEN 6
                    WHEN member <= 1000 THEN 7
                    ELSE 8
                END AS band_id,
                COUNT(*) AS room_count,
                SUM(member) AS total_members
            FROM open_chat
            GROUP BY band_id
            ORDER BY band_id"
        );

        return array_map(fn(array $row) => [
            'band_id' => (int) $row['band_id'],
            'band_label' => self::BAND_LABELS[(int) $row['band_id']],
            'room_count' => (int) $row['room_count'],
            'total_members' => (int) $row['total_members'],
        ], $rows);
    }

    /**
     * 全ルームの参加者数の中央値を取得（MySQL open_chat テーブルから）
     */
    public function getOverallMedian(): int
    {
        return (int) DB::fetchColumn(
            "WITH ranked AS (
                SELECT member,
                       ROW_NUMBER() OVER (ORDER BY member) AS rn,
                       COUNT(*) OVER () AS total
                FROM open_chat
            )
            SELECT ROUND(AVG(member)) FROM ranked
            WHERE rn IN (FLOOR((total + 1) / 2), CEIL((total + 1) / 2))"
        );
    }

    /**
     * カテゴリー別のルーム数・参加者数・中央値・1ヶ月増減を一括取得
     *
     * MySQL: カテゴリー別 room_count, total_members, median
     * SQLite: カテゴリー別 1ヶ月増減（openchat_master JOIN daily_member_statistics）
     * PHP側でマージして返す
     *
     * @return array{ category: int, room_count: int, total_members: int, median: int, monthly_trend: int }[]
     */
    public function getCategoryStatsWithTrend(): array
    {
        // MySQL: カテゴリー別 room_count, total_members, median
        $mysqlStats = DB::fetchAll(
            "SELECT s.category, s.room_count, s.total_members, ROUND(AVG(m.member)) AS median
            FROM (
                SELECT category, COUNT(*) AS room_count, SUM(member) AS total_members
                FROM open_chat
                WHERE category IS NOT NULL
                GROUP BY category
            ) s
            JOIN (
                SELECT category, member,
                    ROW_NUMBER() OVER (PARTITION BY category ORDER BY member) AS rn,
                    COUNT(*) OVER (PARTITION BY category) AS total
                FROM open_chat
                WHERE category IS NOT NULL
            ) m ON s.category = m.category
                AND m.rn IN (FLOOR((m.total + 1) / 2), CEIL((m.total + 1) / 2))
            GROUP BY s.category
            ORDER BY s.total_members DESC, s.category ASC"
        );

        // SQLite: カテゴリー別 1ヶ月増減
        $today = OpenChatServicesUtility::getCronModifiedStatsMemberDate();

        SQLiteOcgraphSqlapi::connect(['mode' => '?mode=ro']);

        // SQLiteのnamed parameterは同一名を複数回バインドできないため、today2~4を別名で渡す
        $trendRows = SQLiteOcgraphSqlapi::fetchAll(
            "SELECT
                om.category_id,
                COALESCE(SUM(CASE WHEN dms.statistics_date = :today THEN dms.member_count END), 0) -
                COALESCE(SUM(CASE WHEN dms.statistics_date = date(:today2, '-1 month') THEN dms.member_count END), 0) AS monthly_trend
            FROM openchat_master om
            JOIN daily_member_statistics dms ON om.openchat_id = dms.openchat_id
            WHERE dms.statistics_date IN (:today3, date(:today4, '-1 month'))
            GROUP BY om.category_id",
            ['today' => $today, 'today2' => $today, 'today3' => $today, 'today4' => $today]
        );

        SQLiteOcgraphSqlapi::$pdo = null;

        // トレンドをカテゴリーIDでインデックス
        $trendByCategory = [];
        foreach ($trendRows as $row) {
            $trendByCategory[(int) $row['category_id']] = (int) $row['monthly_trend'];
        }

        // マージ
        return array_map(fn(array $row) => [
            'category' => (int) $row['category'],
            'room_count' => (int) $row['room_count'],
            'total_members' => (int) $row['total_members'],
            'median' => (int) $row['median'],
            'monthly_trend' => $trendByCategory[(int) $row['category']] ?? 0,
        ], $mysqlStats);
    }
}

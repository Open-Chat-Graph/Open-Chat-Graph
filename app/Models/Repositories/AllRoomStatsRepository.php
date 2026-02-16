<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Models\SQLite\SQLiteOcgraphSqlapi;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;

class AllRoomStatsRepository implements AllRoomStatsRepositoryInterface
{
    private const BAND_LABELS = [
        1 => '1~50人',
        2 => '51~100人',
        3 => '101~200人',
        4 => '201~500人',
        5 => '501~1000人',
        6 => '1001~3000人',
        7 => '3001人以上',
    ];

    public function getTotalRoomCount(): int
    {
        return (int) DB::execute(
            'SELECT COUNT(*) FROM open_chat'
        )->fetchColumn();
    }

    public function getTotalMemberCount(): int
    {
        return (int) DB::execute(
            'SELECT SUM(member) FROM open_chat'
        )->fetchColumn();
    }

    public function getTrackingStartDate(): ?string
    {
        $result = DB::execute(
            'SELECT MIN(created_at) FROM open_chat'
        )->fetchColumn();

        return $result !== false ? (string) $result : null;
    }

    public function getNewRoomCountSince(string $interval): int
    {
        return (int) DB::execute(
            "SELECT COUNT(*) FROM open_chat WHERE created_at >= NOW() - INTERVAL {$interval}"
        )->fetchColumn();
    }

    public function getDeletedRoomCountSince(string $interval): int
    {
        return (int) DB::execute(
            "SELECT COUNT(*) FROM open_chat_deleted WHERE deleted_at >= NOW() - INTERVAL {$interval}"
        )->fetchColumn();
    }

    public function getMemberTrend(string $modifier): int
    {
        $today = OpenChatServicesUtility::getCronModifiedStatsMemberDate();

        SQLiteOcgraphSqlapi::connect(['mode' => '?mode=ro']);

        $totalNow = (int) SQLiteOcgraphSqlapi::fetchColumn(
            "SELECT COALESCE(SUM(member_count), 0) FROM daily_member_statistics WHERE statistics_date = :today",
            ['today' => $today]
        );

        $totalPast = (int) SQLiteOcgraphSqlapi::fetchColumn(
            "SELECT COALESCE(SUM(member_count), 0) FROM daily_member_statistics WHERE statistics_date = date(:today, :modifier)",
            ['today' => $today, 'modifier' => $modifier]
        );

        SQLiteOcgraphSqlapi::$pdo = null;

        return $totalNow - $totalPast;
    }

    public function getDeletedMemberCountSince(string $interval): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$interval}"));

        SQLiteOcgraphSqlapi::connect(['mode' => '?mode=ro']);

        $result = (int) SQLiteOcgraphSqlapi::fetchColumn(
            "SELECT COALESCE(SUM(om.current_member_count), 0)
            FROM open_chat_deleted ocd
            JOIN openchat_master om ON ocd.id = om.openchat_id
            WHERE ocd.deleted_at >= :cutoff",
            ['cutoff' => $cutoff]
        );

        SQLiteOcgraphSqlapi::$pdo = null;

        return $result;
    }

    public function getDelistedStats(string $modifier): array
    {
        $today = OpenChatServicesUtility::getCronModifiedStatsMemberDate();

        SQLiteOcgraphSqlapi::connect(['mode' => '?mode=ro']);

        $pastDate = date('Y-m-d', strtotime($modifier, strtotime($today)));

        $result = SQLiteOcgraphSqlapi::execute(
            "SELECT COUNT(DISTINCT openchat_id) AS rooms, COALESCE(SUM(member_count), 0) AS members
            FROM daily_member_statistics
            WHERE statistics_date = date(:today, :modifier)
            AND openchat_id NOT IN (
                SELECT openchat_id FROM daily_member_statistics
                WHERE statistics_date = :today
            )
            AND openchat_id NOT IN (
                SELECT id FROM open_chat_deleted
                WHERE deleted_at >= :past_date
            )",
            ['today' => $today, 'modifier' => $modifier, 'past_date' => $pastDate]
        )->fetch(\PDO::FETCH_ASSOC);

        SQLiteOcgraphSqlapi::$pdo = null;

        return [
            'rooms' => (int) ($result['rooms'] ?? 0),
            'members' => (int) ($result['members'] ?? 0),
        ];
    }

    /**
     * @return array{ band_id: int, band_label: string, room_count: int, total_members: int }[]
     */
    public function getMemberDistribution(): array
    {
        $rows = DB::fetchAll(
            "SELECT
                CASE
                    WHEN member <= 50 THEN 1
                    WHEN member <= 100 THEN 2
                    WHEN member <= 200 THEN 3
                    WHEN member <= 500 THEN 4
                    WHEN member <= 1000 THEN 5
                    WHEN member <= 3000 THEN 6
                    ELSE 7
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
     * @return array{ category: int, room_count: int, total_members: int, median: int, monthly_trend: int }[]
     */
    public function getCategoryStatsWithMedianAndTrend(): array
    {
        // MySQL: カテゴリー別 room_count, total_members, median
        $mysqlStats = DB::fetchAll(
            "WITH ranked AS (
                SELECT
                    category,
                    member,
                    ROW_NUMBER() OVER (PARTITION BY category ORDER BY member) AS rn,
                    COUNT(*) OVER (PARTITION BY category) AS cnt
                FROM open_chat
                WHERE category IS NOT NULL
            )
            SELECT
                category,
                MAX(cnt) AS room_count,
                SUM(member) AS total_members,
                ROUND(AVG(CASE WHEN rn IN (FLOOR((cnt + 1) / 2), CEIL((cnt + 1) / 2)) THEN member END)) AS median
            FROM ranked
            GROUP BY category
            ORDER BY total_members DESC, category ASC"
        );

        // SQLite: カテゴリー別 1ヶ月増減
        $today = OpenChatServicesUtility::getCronModifiedStatsMemberDate();

        SQLiteOcgraphSqlapi::connect(['mode' => '?mode=ro']);

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

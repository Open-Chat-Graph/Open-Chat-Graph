<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Models\SQLite\SQLiteOcgraphSqlapi;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;

class AllRoomStatsRepository implements AllRoomStatsRepositoryInterface
{
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

    /**
     * @return array{ category: int, room_count: int, total_members: int }[]
     */
    public function getCategoryStats(): array
    {
        return DB::fetchAll(
            'SELECT
                category,
                COUNT(*) AS room_count,
                SUM(member) AS total_members
            FROM
                open_chat
            WHERE
                category IS NOT NULL
            GROUP BY
                category
            ORDER BY
                total_members DESC,
                category ASC'
        );
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

        $result = SQLiteOcgraphSqlapi::execute(
            "SELECT COUNT(DISTINCT openchat_id) AS rooms, COALESCE(SUM(member_count), 0) AS members
            FROM daily_member_statistics
            WHERE statistics_date = date(:today, :modifier)
            AND openchat_id NOT IN (
                SELECT openchat_id FROM daily_member_statistics
                WHERE statistics_date = :today
            )",
            ['today' => $today, 'modifier' => $modifier]
        )->fetch(\PDO::FETCH_ASSOC);

        SQLiteOcgraphSqlapi::$pdo = null;

        return [
            'rooms' => (int) ($result['rooms'] ?? 0),
            'members' => (int) ($result['members'] ?? 0),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Models\RankingPositionDB\RankingPositionDB;
use App\Models\SQLite\SQLiteOcgraphSqlapi;
use App\Models\SQLite\SQLiteStatistics;

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

    public function getHourlyMemberTrend(string $hourModifier): int
    {
        RankingPositionDB::connect();

        $latestTime = (string) RankingPositionDB::fetchColumn(
            "SELECT MAX(time) FROM member"
        );

        if (!$latestTime) {
            RankingPositionDB::$pdo = null;
            return 0;
        }

        $pastDateTime = new \DateTime($latestTime);
        $pastDateTime->modify($hourModifier);
        $pastTimeStr = $pastDateTime->format('Y-m-d H:i:s');

        $actualPastTime = (string) RankingPositionDB::fetchColumn(
            "SELECT MAX(time) FROM member WHERE time <= :past",
            ['past' => $pastTimeStr]
        );

        if (!$actualPastTime) {
            RankingPositionDB::$pdo = null;
            return 0;
        }

        $totalNow = (int) RankingPositionDB::fetchColumn(
            "SELECT COALESCE(SUM(member), 0) FROM member WHERE time = :time",
            ['time' => $latestTime]
        );

        $totalPast = (int) RankingPositionDB::fetchColumn(
            "SELECT COALESCE(SUM(member), 0) FROM member WHERE time = :time",
            ['time' => $actualPastTime]
        );

        RankingPositionDB::$pdo = null;

        return $totalNow - $totalPast;
    }

    public function getDailyMemberTrend(string $dateModifier): int
    {
        $today = date('Y-m-d');

        SQLiteStatistics::connect(['mode' => '?mode=ro']);

        $totalNow = (int) SQLiteStatistics::fetchColumn(
            "SELECT COALESCE(SUM(member), 0) FROM statistics WHERE date = date(:today)",
            ['today' => $today]
        );

        $totalPast = (int) SQLiteStatistics::fetchColumn(
            "SELECT COALESCE(SUM(member), 0) FROM statistics WHERE date = date(:today, :modifier)",
            ['today' => $today, 'modifier' => $dateModifier]
        );

        SQLiteStatistics::$pdo = null;

        return $totalNow - $totalPast;
    }

    public function getDeletedMemberCountSince(string $interval): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$interval}"));
        return (int) SQLiteOcgraphSqlapi::fetchColumn(
            "SELECT COALESCE(SUM(om.current_member_count), 0)
            FROM open_chat_deleted ocd
            JOIN openchat_master om ON ocd.id = om.openchat_id
            WHERE ocd.deleted_at >= :cutoff",
            ['cutoff' => $cutoff]
        );
    }
}

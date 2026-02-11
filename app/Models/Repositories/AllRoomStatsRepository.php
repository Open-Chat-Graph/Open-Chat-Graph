<?php

declare(strict_types=1);

namespace App\Models\Repositories;

class AllRoomStatsRepository
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

    public function getEarliestDeletedDate(): ?string
    {
        $result = DB::execute(
            'SELECT MIN(deleted_at) FROM open_chat_deleted'
        )->fetchColumn();

        return $result !== false ? (string) $result : null;
    }

    public function getDeletedRoomCount(): int
    {
        return (int) DB::execute(
            'SELECT COUNT(*) FROM open_chat_deleted'
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

    public function getHourlyMemberIncrease(): int
    {
        return (int) DB::execute(
            'SELECT COALESCE(SUM(diff_member), 0) FROM statistics_ranking_hour WHERE diff_member > 0'
        )->fetchColumn();
    }

    public function getDailyMemberIncrease(): int
    {
        return (int) DB::execute(
            'SELECT COALESCE(SUM(diff_member), 0) FROM statistics_ranking_hour24 WHERE diff_member > 0'
        )->fetchColumn();
    }

    public function getWeeklyMemberIncrease(): int
    {
        return (int) DB::execute(
            'SELECT COALESCE(SUM(diff_member), 0) FROM statistics_ranking_week WHERE diff_member > 0'
        )->fetchColumn();
    }
}

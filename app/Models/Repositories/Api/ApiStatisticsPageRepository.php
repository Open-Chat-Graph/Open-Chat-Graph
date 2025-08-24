<?php

declare(strict_types=1);

namespace App\Models\Repositories\Api;

use App\Models\Repositories\Statistics\StatisticsPageRepositoryInterface;

/**
 * Repository for statistics page data from ocgraph_sqlapi database
 * Implements StatisticsPageRepositoryInterface using data imported by OcreviewApiDataImporter
 */
class ApiStatisticsPageRepository implements StatisticsPageRepositoryInterface
{
    /**
     * Get daily member statistics in date ascending order
     * Returns array of statistics records with date and member count
     * 
     * @param int $open_chat_id OpenChat ID
     * @return array{date: string, member: int}[] Array of statistics records
     */
    function getDailyMemberStatsDateAsc(int $open_chat_id): array
    {
        ApiDB::connect();

        $query =
            "SELECT 
                statistics_date AS date,
                member_count AS member
            FROM 
                daily_member_statistics
            WHERE 
                openchat_id = :open_chat_id
            ORDER BY 
                statistics_date ASC";

        $result = ApiDB::fetchAll($query, compact('open_chat_id'));
        if (empty($result)) {
            return [];
        }

        $currentMemberCount = ApiDB::fetchColumn(
            "SELECT
                current_member_count
            FROM 
                openchat_master 
            WHERE 
                openchat_id = :open_chat_id",
            compact('open_chat_id')
        );

        if ($currentMemberCount === $result[count($result) - 1]['member']) {
            return $result;
        }

        $lastDate = new \DateTime($result[count($result) - 1]['date']);
        $lastDate->modify('+1 day');

        $newStats = [
            'date' => $lastDate->format('Y-m-d'),
            'member' => $currentMemberCount
        ];

        return array_merge($result, [$newStats]);
    }
}

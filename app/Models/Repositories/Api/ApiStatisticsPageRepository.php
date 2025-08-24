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

        $query = "
            SELECT 
                statistics_date AS date,
                member_count AS member
            FROM 
                daily_member_statistics
            WHERE 
                openchat_id = ?
            ORDER BY 
                statistics_date ASC
        ";

        $stmt = ApiDB::$pdo->prepare($query);
        $stmt->execute([$open_chat_id]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($results)) {
            return [];
        }

        // Convert member count to integer
        return array_map(function ($row) {
            return [
                'date' => $row['date'],
                'member' => (int)$row['member']
            ];
        }, $results);
    }
}

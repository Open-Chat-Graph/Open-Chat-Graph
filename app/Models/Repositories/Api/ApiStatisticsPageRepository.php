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
     * Get daily statistics data for a specific period
     * Returns data formatted for chart display with separate date and member arrays
     * 
     * @param int $open_chat_id OpenChat ID
     * @return array{date: string[], member: int[]} Chart data arrays
     */
    function getDailyStatisticsByPeriod(int $open_chat_id): array
    {
        ApiDB::connect();
        
        $query = "
            SELECT 
                statistics_date,
                member_count
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
            return ['date' => [], 'member' => []];
        }
        
        $dates = [];
        $members = [];
        
        foreach ($results as $row) {
            $dates[] = $row['statistics_date'];
            $members[] = (int)$row['member_count'];
        }
        
        return [
            'date' => $dates,
            'member' => $members
        ];
    }

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
        return array_map(function($row) {
            return [
                'date' => $row['date'],
                'member' => (int)$row['member']
            ];
        }, $results);
    }

    /**
     * Get member count for a specific date
     * 
     * @param int $open_chat_id OpenChat ID
     * @param string $date Date in Y-m-d format
     * @return int|false Member count or false if not found
     */
    function getMemberCount(int $open_chat_id, string $date): int|false
    {
        ApiDB::connect();
        
        $query = "
            SELECT 
                member_count
            FROM 
                daily_member_statistics
            WHERE 
                openchat_id = ? 
                AND statistics_date = ?
            LIMIT 1
        ";
        
        $stmt = ApiDB::$pdo->prepare($query);
        $stmt->execute([$open_chat_id, $date]);
        $result = $stmt->fetchColumn();
        
        return $result !== false ? (int)$result : false;
    }
}
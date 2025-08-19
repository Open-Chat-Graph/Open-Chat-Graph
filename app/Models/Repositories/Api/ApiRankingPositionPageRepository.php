<?php

declare(strict_types=1);

namespace App\Models\Repositories\Api;

use App\Models\Repositories\RankingPosition\Dto\RankingPositionPageRepoDto;
use App\Models\Repositories\RankingPosition\RankingPositionPageRepositoryInterface;
use App\Services\OpenChat\Enum\RankingType;

/**
 * Repository for ranking position data from ocgraph_sqlapi database
 * Implements RankingPositionPageRepositoryInterface using data imported by OcreviewApiDataImporter
 */
class ApiRankingPositionPageRepository implements RankingPositionPageRepositoryInterface
{
    /**
     * Get daily position data for a specific ranking type
     * 
     * @param RankingType $type Ranking type (ranking or rising)
     * @param int $open_chat_id OpenChat ID
     * @param int $category Category ID
     * @return RankingPositionPageRepoDto DTO containing time, position, and total count arrays
     */
    public function getDailyPosition(
        RankingType $type,
        int $open_chat_id,
        int $category
    ): RankingPositionPageRepoDto {
        $dto = new RankingPositionPageRepoDto;
        
        ApiDB::connect();
        
        // Determine table based on ranking type
        $tableName = $type === RankingType::Ranking 
            ? 'line_official_activity_ranking_history' 
            : 'line_official_activity_trending_history';
        
        $positionColumn = $type === RankingType::Ranking
            ? 'activity_ranking_position'
            : 'activity_trending_position';
            
        $totalCountColumn = $type === RankingType::Ranking
            ? 'activity_ranking_total_count'
            : 'activity_trending_total_count';
        
        $query = "
            SELECT 
                h.recorded_at AS time,
                h.{$positionColumn} AS position,
                tc.{$totalCountColumn} AS total_count
            FROM 
                {$tableName} h
                JOIN line_official_ranking_total_count tc 
                    ON h.recorded_at = tc.recorded_at 
                    AND h.category_id = tc.category_id
            WHERE 
                h.openchat_id = ?
                AND h.category_id = ?
            ORDER BY 
                h.recorded_at ASC
        ";
        
        $stmt = ApiDB::$pdo->prepare($query);
        $stmt->execute([$open_chat_id, $category]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($results)) {
            return $dto;
        }
        
        $dto->time = array_column($results, 'time');
        $dto->position = array_map('intval', array_column($results, 'position'));
        $dto->totalCount = array_map('intval', array_column($results, 'total_count'));
        
        return $dto;
    }
    
    /**
     * Get the final ranking position for an OpenChat
     * 
     * @param int $open_chat_id OpenChat ID
     * @param int $category Category ID
     * @return array{time:string,position:int,total_count_ranking:int}|false
     */
    public function getFinalRankingPosition(int $open_chat_id, int $category): array|false
    {
        ApiDB::connect();
        
        $query = "
            SELECT 
                h.recorded_at AS time,
                h.activity_ranking_position AS position,
                tc.activity_ranking_total_count AS total_count_ranking
            FROM 
                line_official_activity_ranking_history h
                JOIN line_official_ranking_total_count tc 
                    ON h.recorded_at = tc.recorded_at 
                    AND h.category_id = tc.category_id
            WHERE 
                h.openchat_id = ?
                AND h.category_id = ?
            ORDER BY 
                h.recorded_at DESC
            LIMIT 1
        ";
        
        $stmt = ApiDB::$pdo->prepare($query);
        $stmt->execute([$open_chat_id, $category]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($result === false) {
            return false;
        }
        
        return [
            'time' => $result['time'],
            'position' => (int)$result['position'],
            'total_count_ranking' => (int)$result['total_count_ranking']
        ];
    }
}
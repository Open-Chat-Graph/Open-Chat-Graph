<?php

declare(strict_types=1);

namespace App\Models\SQLite\Repositories\RankingPosition;

use App\Models\Repositories\RankingPosition\Dto\RankingPositionPageRepoDto;
use App\Models\Repositories\RankingPosition\RankingPositionPageRepositoryInterface;
use App\Models\SQLite\SQLiteRankingPosition;
use App\Services\OpenChat\Enum\RankingType;

class SqliteRankingPositionPageRepository implements RankingPositionPageRepositoryInterface
{
    public function getDailyPosition(
        RankingType $type,
        int $open_chat_id,
        int $category,
    ): RankingPositionPageRepoDto {
        $tableName = $type->value;
        $query =
            "SELECT
                t1.time AS time,
                t1.position AS position,
                t2.total_count_{$tableName} AS total_count
            FROM
                (
                    SELECT
                        *
                    FROM
                        {$tableName}
                    WHERE
                        open_chat_id = :open_chat_id
                        AND category = :category
                ) AS t1
                JOIN total_count AS t2 ON t1.time = t2.time
                AND t1.category = t2.category
            ORDER BY
                t1.time ASC";

        $dto = new RankingPositionPageRepoDto;

        SQLiteRankingPosition::connect(['mode' => '?mode=ro&nolock=1']);

        $result = SQLiteRankingPosition::fetchAll($query, compact('open_chat_id', 'category'));

        SQLiteRankingPosition::$pdo = null;

        if (!$result) {
            return $dto;
        }

        $dto->time = array_column($result, 'time');
        $dto->position = array_column($result, 'position');
        $dto->totalCount = array_column($result, 'total_count');

        return $dto;
    }

    public function getFinalRankingPosition(int $open_chat_id, int $category): array|false
    {
        $query =
            "SELECT
                r.time AS time,
                r.position AS position,
                tc.total_count_ranking AS total_count_ranking
            FROM
                ranking AS r
                JOIN total_count AS tc ON tc.category = r.category
                AND r.time = tc.time
            WHERE
                r.open_chat_id = {$open_chat_id}
                AND r.category = {$category}
            ORDER BY
                time DESC
            LIMIT 1";

        return SQLiteRankingPosition::fetch($query);
    }
}

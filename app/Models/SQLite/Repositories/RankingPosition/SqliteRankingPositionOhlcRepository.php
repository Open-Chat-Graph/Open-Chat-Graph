<?php

declare(strict_types=1);

namespace App\Models\SQLite\Repositories\RankingPosition;

use App\Models\Repositories\RankingPosition\RankingPositionOhlcRepositoryInterface;
use App\Models\SQLite\SQLiteInsertImporter;
use App\Models\SQLite\SQLiteRankingPositionOhlc;
use App\Services\OpenChat\Enum\RankingType;

class SqliteRankingPositionOhlcRepository implements RankingPositionOhlcRepositoryInterface
{
    public function insertOhlc(array $data): int
    {
        /** @var SQLiteInsertImporter $inserter */
        $inserter = app(SQLiteInsertImporter::class);

        return $inserter->import(SQLiteRankingPositionOhlc::connect(), 'ranking_position_ohlc', $data, 500);
    }

    public function getOhlcDateAsc(int $open_chat_id, int $category, RankingType $type): array
    {
        $typeValue = $type->value;

        $query =
            "SELECT
                date,
                open_position,
                high_position,
                low_position,
                close_position
            FROM
                ranking_position_ohlc
            WHERE
                open_chat_id = :open_chat_id
                AND category = :category
                AND type = :type
            ORDER BY
                date ASC";

        SQLiteRankingPositionOhlc::connect(['mode' => '?mode=ro']);
        $result = SQLiteRankingPositionOhlc::fetchAll($query, ['open_chat_id' => $open_chat_id, 'category' => $category, 'type' => $typeValue]);
        SQLiteRankingPositionOhlc::$pdo = null;

        return $result;
    }
}

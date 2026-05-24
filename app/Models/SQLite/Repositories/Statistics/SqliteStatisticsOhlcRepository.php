<?php

declare(strict_types=1);

namespace App\Models\SQLite\Repositories\Statistics;

use App\Models\Repositories\Statistics\StatisticsOhlcRepositoryInterface;
use App\Models\SQLite\SQLiteInsertImporter;
use App\Models\SQLite\SQLiteStatisticsOhlc;

class SqliteStatisticsOhlcRepository implements StatisticsOhlcRepositoryInterface
{
    public function insertOhlc(array $data): int
    {
        /** @var SQLiteInsertImporter $inserter */
        $inserter = app(SQLiteInsertImporter::class);

        return $inserter->import(SQLiteStatisticsOhlc::connect(), 'statistics_ohlc', $data, 500);
    }

    public function getOhlcDateAsc(int $open_chat_id): array
    {
        $query =
            "SELECT
                date,
                open_member,
                high_member,
                low_member,
                close_member
            FROM
                statistics_ohlc
            WHERE
                open_chat_id = :open_chat_id
            ORDER BY
                date ASC";

        SQLiteStatisticsOhlc::connect(['mode' => '?mode=ro']);
        $result = SQLiteStatisticsOhlc::fetchAll($query, compact('open_chat_id'));
        SQLiteStatisticsOhlc::$pdo = null;

        return $result;
    }
}

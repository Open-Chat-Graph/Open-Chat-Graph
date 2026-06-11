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

        return $result;
    }

    public function getOhlcCounts(int $open_chat_id, string $weekStartDate, string $monthStartDate): array
    {
        $query =
            "SELECT
                COUNT(*) AS all_count,
                COUNT(CASE WHEN date >= :week_start_date THEN 1 END) AS week_count,
                COUNT(CASE WHEN date >= :month_start_date THEN 1 END) AS month_count
            FROM
                statistics_ohlc
            WHERE
                open_chat_id = :open_chat_id";

        $emptyResult = ['all_count' => 0, 'week_count' => 0, 'month_count' => 0];

        try {
            SQLiteStatisticsOhlc::connect(['mode' => '?mode=ro']);
        } catch (\PDOException) {
            // DBファイル未作成（OHLC統計の記録開始前の環境）は「データ無し」として扱う
            return $emptyResult;
        }

        $result = SQLiteStatisticsOhlc::fetch($query, [
            'open_chat_id' => $open_chat_id,
            'week_start_date' => $weekStartDate,
            'month_start_date' => $monthStartDate,
        ]);

        if (!is_array($result)) {
            return $emptyResult;
        }

        return [
            'all_count' => (int)$result['all_count'],
            'week_count' => (int)$result['week_count'],
            'month_count' => (int)$result['month_count'],
        ];
    }
}

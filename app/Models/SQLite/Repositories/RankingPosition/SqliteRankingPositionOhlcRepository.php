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

    public function getRecentPositionMovement(int $open_chat_id, int $category, RankingType $type, int $days): array
    {
        $typeValue = $type->value;
        // 範囲内の close_position 最古値・最新値・最高位を 1 クエリで集約
        $query =
            "WITH s AS (
                SELECT date, open_position, high_position, low_position, close_position
                  FROM ranking_position_ohlc
                 WHERE open_chat_id = :open_chat_id
                   AND category = :category
                   AND type = :type
                   AND date >= date('now', :since)
            )
            SELECT
                (SELECT close_position FROM s ORDER BY date ASC  LIMIT 1) AS oldest_close,
                (SELECT date           FROM s ORDER BY date ASC  LIMIT 1) AS oldest_date,
                (SELECT close_position FROM s ORDER BY date DESC LIMIT 1) AS latest_close,
                (SELECT date           FROM s ORDER BY date DESC LIMIT 1) AS latest_date,
                (SELECT MIN(high_position) FROM s) AS best_high,
                (SELECT COUNT(*)       FROM s) AS sample_n";

        $since = '-' . max(1, $days) . ' days';

        SQLiteRankingPositionOhlc::connect(['mode' => '?mode=ro']);
        $row = SQLiteRankingPositionOhlc::fetch($query, [
            'open_chat_id' => $open_chat_id,
            'category' => $category,
            'type' => $typeValue,
            'since' => $since,
        ]);
        SQLiteRankingPositionOhlc::$pdo = null;

        if (!$row || !is_array($row)) {
            return [
                'oldest_close' => null,
                'oldest_date' => null,
                'latest_close' => null,
                'latest_date' => null,
                'best_high' => null,
                'sample_n' => 0,
            ];
        }

        return [
            'oldest_close' => $row['oldest_close'] !== null ? (int)$row['oldest_close'] : null,
            'oldest_date'  => $row['oldest_date']  !== null ? (string)$row['oldest_date']  : null,
            'latest_close' => $row['latest_close'] !== null ? (int)$row['latest_close'] : null,
            'latest_date'  => $row['latest_date']  !== null ? (string)$row['latest_date']  : null,
            'best_high'    => $row['best_high']    !== null ? (int)$row['best_high']    : null,
            'sample_n'     => (int)($row['sample_n'] ?? 0),
        ];
    }
}

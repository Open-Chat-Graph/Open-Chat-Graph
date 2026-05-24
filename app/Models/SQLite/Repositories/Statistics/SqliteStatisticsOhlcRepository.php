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

    public function getMemberMetricsForNarrative(int $open_chat_id): array
    {
        // 直近 200 件を母集団にし、各種期間値・ピーク・単日最大伸びを 1 クエリで集約。
        // CTE で対象行を 1 度だけ走査することで I/O を抑える。
        $query =
            "WITH s AS (
                SELECT date, open_member, high_member, low_member, close_member
                  FROM statistics_ohlc
                 WHERE open_chat_id = :open_chat_id
                 ORDER BY date DESC
                 LIMIT 200
            )
            SELECT
                (SELECT close_member FROM s ORDER BY date DESC LIMIT 1) AS curr,
                (SELECT date         FROM s ORDER BY date DESC LIMIT 1) AS curr_date,
                (SELECT close_member FROM s WHERE date <= date('now','-7 days')  ORDER BY date DESC LIMIT 1) AS m7,
                (SELECT close_member FROM s WHERE date <= date('now','-30 days') ORDER BY date DESC LIMIT 1) AS m30,
                (SELECT close_member FROM s WHERE date <= date('now','-90 days') ORDER BY date DESC LIMIT 1) AS m90,
                (SELECT COUNT(*)     FROM s) AS sample_n,
                (SELECT MAX(high_member) FROM s) AS peak_high,
                (SELECT date FROM s ORDER BY high_member DESC, date DESC LIMIT 1) AS peak_date,
                (SELECT MAX(close_member - open_member) FROM s) AS max_single_day_growth,
                (SELECT date FROM s WHERE (close_member - open_member) > 0 ORDER BY (close_member - open_member) DESC, date DESC LIMIT 1) AS max_growth_date,
                (SELECT date FROM s ORDER BY date ASC LIMIT 1) AS first_date";

        SQLiteStatisticsOhlc::connect(['mode' => '?mode=ro']);
        $row = SQLiteStatisticsOhlc::fetch($query, compact('open_chat_id'));
        SQLiteStatisticsOhlc::$pdo = null;

        if (!$row || !is_array($row)) {
            return [
                'curr' => null,
                'curr_date' => null,
                'm7' => null,
                'm30' => null,
                'm90' => null,
                'sample_n' => 0,
                'peak_high' => null,
                'peak_date' => null,
                'max_single_day_growth' => null,
                'max_growth_date' => null,
                'first_date' => null,
            ];
        }

        return [
            'curr'                 => $row['curr'] !== null ? (int)$row['curr'] : null,
            'curr_date'            => $row['curr_date'] !== null ? (string)$row['curr_date'] : null,
            'm7'                   => $row['m7']   !== null ? (int)$row['m7']   : null,
            'm30'                  => $row['m30']  !== null ? (int)$row['m30']  : null,
            'm90'                  => $row['m90']  !== null ? (int)$row['m90']  : null,
            'sample_n'             => (int)($row['sample_n'] ?? 0),
            'peak_high'            => $row['peak_high'] !== null ? (int)$row['peak_high'] : null,
            'peak_date'            => $row['peak_date'] !== null ? (string)$row['peak_date'] : null,
            'max_single_day_growth' => $row['max_single_day_growth'] !== null ? (int)$row['max_single_day_growth'] : null,
            'max_growth_date'      => $row['max_growth_date'] !== null ? (string)$row['max_growth_date'] : null,
            'first_date'           => $row['first_date'] !== null ? (string)$row['first_date'] : null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models\SQLite\Repositories\Statistics;

use App\Models\Repositories\Statistics\StatisticsPageRepositoryInterface;
use App\Models\SQLite\SQLiteStatistics;

class SqliteStatisticsPageRepository implements StatisticsPageRepositoryInterface
{
    public function getDailyMemberStatsDateAsc(int $open_chat_id, ?string $from = null, ?string $to = null): array
    {
        // from/to が両方揃ったときだけ範囲で絞る（片方欠けは従来どおり全期間）
        $useRange = $from !== null && $to !== null;
        $rangeClause = $useRange ? "\n                AND date BETWEEN :from AND :to" : '';

        $query =
            "SELECT
                date,
                member
            FROM
                statistics
            WHERE
                open_chat_id = :open_chat_id{$rangeClause}
            ORDER BY
                date ASC";

        $params = compact('open_chat_id');
        if ($useRange) {
            $params['from'] = $from;
            $params['to'] = $to;
        }

        SQLiteStatistics::connect(SQLiteStatistics::WEB_READER);
        $result = SQLiteStatistics::fetchAll($query, $params);

        return $result;
    }

    public function getMemberDateRange(int $open_chat_id): ?array
    {
        $query =
            "SELECT
                MIN(date) AS min,
                MAX(date) AS max
            FROM
                statistics
            WHERE
                open_chat_id = :open_chat_id";

        SQLiteStatistics::connect(SQLiteStatistics::WEB_READER);
        $result = SQLiteStatistics::fetch($query, compact('open_chat_id'));

        // 集約クエリは行を必ず1つ返すが、対象が無いと MIN/MAX は NULL になる
        if (!is_array($result) || $result['min'] === null || $result['max'] === null) {
            return null;
        }

        return ['min' => (string)$result['min'], 'max' => (string)$result['max']];
    }
}

<?php

declare(strict_types=1);

namespace App\Models\SQLite\Repositories\Statistics;

use App\Models\Repositories\Statistics\StatisticsPageRepositoryInterface;
use App\Models\SQLite\SQLiteStatistics;

class SqliteStatisticsPageRepository implements StatisticsPageRepositoryInterface
{
    public function getDailyMemberStatsDateAsc(int $open_chat_id): array
    {
        $query =
            "SELECT
                date,
                member
            FROM
                statistics
            WHERE
                open_chat_id = :open_chat_id
            ORDER BY
                date ASC";

        SQLiteStatistics::connect(SQLiteStatistics::WEB_READER);
        $result = SQLiteStatistics::fetchAll($query, compact('open_chat_id'));

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

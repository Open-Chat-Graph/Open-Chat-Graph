<?php

declare(strict_types=1);

namespace App\Models\Repositories\Recommend;

use App\Models\Repositories\DB;

/**
 * @see TrendingThemeRepositoryInterface
 */
class TrendingThemeRepository implements TrendingThemeRepositoryInterface
{
    public function fetchRisingRoomsByHour(): array
    {
        return DB::fetchAll(
            "SELECT
                t2.tag, t1.diff_member
            FROM
                recommend AS t2
                JOIN statistics_ranking_hour AS t1 ON t1.open_chat_id = t2.id
                LEFT JOIN statistics_ranking_hour24 AS t3 ON t3.open_chat_id = t1.open_chat_id
            WHERE
                t1.diff_member >= 4 AND t3.diff_member >= 4"
        );
    }

    public function fetchRisingRoomsByHourAnd24h(): array
    {
        return DB::fetchAll(
            "SELECT
                t2.tag, t1.diff_member
            FROM
                recommend AS t2
                JOIN statistics_ranking_hour AS t1 ON t1.open_chat_id = t2.id
                LEFT JOIN statistics_ranking_hour24 AS t3 ON t3.open_chat_id = t1.open_chat_id
            WHERE
                t1.diff_member >= 3
                AND t3.diff_member >= 10"
        );
    }

    public function fetchRisingRoomsByDay(): array
    {
        return DB::fetchAll(
            "SELECT
                t2.tag, t1.diff_member
            FROM
                recommend AS t2
                JOIN statistics_ranking_hour24 AS t1 ON t1.open_chat_id = t2.id
                LEFT JOIN statistics_ranking_week AS t3 ON t3.open_chat_id = t1.open_chat_id
            WHERE
                t1.diff_member >= 6
                OR (t3.diff_member >= 10 AND t1.diff_member >= 0)
                OR (t3.diff_member >= 20)"
        );
    }
}

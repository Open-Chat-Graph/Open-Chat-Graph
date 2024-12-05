<?php

declare(strict_types=1);

namespace App\Models\RankingPositionDB\Repositories;

use App\Models\Importer\SqlInsert;
use App\Models\RankingPositionDB\RankingPositionDB;
use App\Models\Repositories\RankingPosition\HourMemberRankingUpdaterRepositoryInterface;
use App\Models\Repositories\DB;

class HourMemberRankingUpdaterRepository implements HourMemberRankingUpdaterRepositoryInterface
{
    public function __construct(
        private SqlInsert $inserter
    ) {
    }

    public function updateHourRankingTable(\DateTime $dateTime, array $filters): int
    {
        $data = $this->buildRankingData($dateTime, $filters, '-1hour');
        if (!$data) {
            return 0;
        }

        DB::execute("TRUNCATE TABLE statistics_ranking_hour");
        $result = $this->inserter->import(DB::connect(), 'statistics_ranking_hour', $data);

        $data = $this->buildRankingData($dateTime, $filters, '-24hour');
        if (!$data) {
            return $result;
        }

        DB::execute("TRUNCATE TABLE statistics_ranking_hour24");
        $result += $this->inserter->import(DB::connect(), 'statistics_ranking_hour24', $data);
        return $result;
    }

    /**
     * @param int[] $filters
     * @return array{ id: int, open_chat_id: int, diff_member: int, percent_increase: float }[]
     */
    public function buildRankingData(\DateTime $dateTime, array $filters, string $modifier): array
    {
        $data = $this->getHourRanking($dateTime, $modifier);
        $result = [];
        $counter = 1; // 連番のカウンタ

        foreach ($data as $row) {
            if (in_array($row['open_chat_id'], $filters, true) || $row['diff_member'] > 0) {
                $row['id'] = $counter++; // idに連番を設定し、カウンタをインクリメント
                unset($row['index1']);
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * @return array{ open_chat_id: int, diff_member: int, percent_increase: float, index1: float }[]
     */
    public function getHourRanking(\DateTime $dateTime, string $modifier): array
    {
        $dateTime2 = new \DateTime($dateTime->format('Y-m-d H:i:s'));
        $dateTime2->modify($modifier);

        $timeString = $dateTime->format('Y-m-d H:i:s');
        $timeString2 = $dateTime2->format('Y-m-d H:i:s');

        $query =
            "SELECT
                t1.open_chat_id AS open_chat_id,
                t1.member - t2.member AS diff_member,
                (
                    (
                        CAST(t1.member AS FLOAT) - CAST(t2.member AS FLOAT)
                    ) * 100.0 / CAST(t2.member AS FLOAT)
                ) AS percent_increase,
                (
                    CAST(t1.member AS FLOAT) - CAST(t2.member AS FLOAT)
                ) + (
                    (
                        (
                            CAST(t1.member AS FLOAT) - CAST(t2.member AS FLOAT)
                        ) / CAST(t2.member AS FLOAT)
                    ) * 10
                ) AS index1
            FROM
                member as t1
                JOIN(
                    SELECT
                        *
                    FROM
                        member AS t3
                    WHERE
                        t3.time = '{$timeString2}'
                        AND t3.member >= 10
                ) AS t2 ON t1.open_chat_id = t2.open_chat_id
            WHERE
                t1.time = '{$timeString}'
                AND t1.member >= 10
            ORDER BY 
                CASE
                    WHEN index1 > 0 THEN 1
                    WHEN index1 = 0 THEN 2
                    ELSE 3
                END,
                CASE
                    WHEN index1 = 0 THEN t1.member
                    ELSE index1
                END DESC";

        return RankingPositionDB::fetchAll($query);
    }
}

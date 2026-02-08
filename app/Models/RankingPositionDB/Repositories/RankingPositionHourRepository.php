<?php

declare(strict_types=1);

namespace App\Models\RankingPositionDB\Repositories;

use App\Config\AppConfig;
use App\Models\Importer\SqlInsert;
use App\Models\RankingPositionDB\RankingPositionDB;
use App\Models\Repositories\RankingPosition\Dto\RankingPositionHourInsertDto;
use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Services\Cron\Utility\CronUtility;
use App\Services\OpenChat\Enum\RankingType;
use Shared\MimimalCmsConfig;

class RankingPositionHourRepository implements RankingPositionHourRepositoryInterface
{
    function __construct(
        private SqlInsert $inserter
    ) {}

    /**
     * @param RankingPositionHourInsertDto[] $insertDtoArray
     */
    public function insertFromDtoArray(RankingType $type, string $fileTime, array $insertDtoArray): int
    {
        $keys = [
            'open_chat_id',
            'position',
            'category',
            'time'
        ];

        $data = array_map(function (RankingPositionHourInsertDto $dto) use ($fileTime) {
            return [
                $dto->open_chat_id,
                $dto->position,
                $dto->category,
                $fileTime
            ];
        }, $insertDtoArray);

        return $this->inserter->importWithKeys(RankingPositionDB::connect(), $type->value, $keys, $data, 250);
    }

    /**
     * @param RankingPositionHourInsertDto[] $insertDtoArray
     */
    public function insertHourMemberFromDtoArray(string $fileTime, array $insertDtoArray): int
    {
        $keys = [
            'open_chat_id',
            'member',
            'time'
        ];

        $data = array_map(function (RankingPositionHourInsertDto $dto) use ($fileTime) {
            return [
                $dto->open_chat_id,
                $dto->member,
                $fileTime
            ];
        }, $insertDtoArray);

        return $this->inserter->importWithKeys(RankingPositionDB::connect(), 'member', $keys, $data, 250);
    }

    public function getDailyMemberStats(\DateTime $todayLastTime): array
    {
        $time = $todayLastTime->format('Y-m-d');

        $query =
            "SELECT
                m.open_chat_id,
                m.member,
                DATE(m.time) AS date
            FROM
                member AS m
            JOIN (
                SELECT
                    open_chat_id,
                    MAX(time) AS max_time
                FROM
                    member
                WHERE
                    DATE(time) = '{$time}'
                GROUP BY
                    open_chat_id
            ) AS latest ON m.open_chat_id = latest.open_chat_id AND m.time = latest.max_time";

        return RankingPositionDB::fetchAll($query);
    }

    public function getHourlyMemberColumn(\DateTime $lastTime): array
    {
        $time = $lastTime->format('Y-m-d H:i:s');

        $query =
            "SELECT
                open_chat_id,
                member
            FROM
                member
            WHERE
                time = '{$time}'";

        return RankingPositionDB::fetchAll($query);
    }

    private function getMinPosition(string $tableName, \DateTime $date, bool $all): array
    {
        $dateString = $date->format('Y-m-d');
        $isAll = $all ? '' : 'NOT';

        $query =
            "SELECT
                subquery.open_chat_id,
                subquery.category,
                subquery.position,
                subquery.time
            FROM
                (
                    SELECT
                        *,
                        ROW_NUMBER() OVER (
                            PARTITION BY open_chat_id
                            ORDER BY
                                position ASC,
                                time DESC
                        ) as rn
                    FROM
                        {$tableName}
                    WHERE
                        DATE(time) = '{$dateString}'
                        AND {$isAll} category = 0
                ) AS subquery
            WHERE
                rn = 1;";

        return RankingPositionDB::fetchAll($query);
    }

    private function getIdArray(string $tableName, string $dateString, bool $all): array
    {
        $isAll = $all ? '' : 'NOT';

        $query =
            "SELECT 
                DISTINCT open_chat_id
            FROM
                {$tableName}
            WHERE
                DATE(time) = '{$dateString}'
                AND {$isAll} category = 0";

        RankingPositionDB::$pdo = null;
        return RankingPositionDB::fetchAll($query, null, [\PDO::FETCH_COLUMN, 0]);
    }

    public function getMedianPositionQuery(string $tableName, int $open_chat_id, string $dateString, bool $all): array|false
    {
        $isAll = $all ? '' : 'NOT';

        $query =
            "SELECT
                open_chat_id,
                category,
                position,
                time
            FROM
                {$tableName}
            WHERE
                DATE(time) = '{$dateString}'
                AND {$isAll} category = 0
                AND open_chat_id = {$open_chat_id}
            ORDER BY
                position ASC";

        /** @var array{ open_chat_id:int, category:int, position:int, time: string }[] $records */
        $records = RankingPositionDB::fetchAll($query);

        $count = count($records);
        if (!$count) return false;
        if ($count === 1) return $records[0];

        $centerCount = $count / 2;

        if (is_float($centerCount)) {
            // 奇数の場合
            $median = $records[(int)ceil($centerCount) - 1];
            $median['position'] = (int)ceil(
                ($median['position'] + $records[(int)floor($centerCount) - 1]['position']) / 2
            );
        } else {
            $median = $records[$centerCount - 1];
        }

        return $median;
    }

    private function getMedianPosition(string $tableName, \DateTime $date, bool $all): array
    {
        $dateString = $date->format('Y-m-d');
        $idArray = $this->getIdArray($tableName, $dateString, $all);

        $result = [];
        foreach ($idArray as $id) {
            $record = $this->getMedianPositionQuery($tableName, $id, $dateString, $all);
            if (!$record) continue;
            $result[] = $record;
        }

        return $result;
    }

    public function getDaliyRanking(\DateTime $date, bool $all = false): array
    {
        return $this->getMedianPosition('ranking', $date, $all);
    }

    public function getDailyRising(\DateTime $date, bool $all = false): array
    {
        return $this->getMinPosition('rising', $date, $all);
    }

    public function getTotalCount(\DateTime $date, bool $isDate = true): array
    {
        if ($isDate) {
            $dateString = $date->format('Y-m-d');
            $where = "DATE(time) = '{$dateString}'";
        } else {
            $timeString = $date->format('Y-m-d H:i:s');
            $where = "time = '{$timeString}'";
        }

        $query =
            "SELECT
                ra.category AS category,
                ifnull(ri.count, 0) AS total_count_rising,
                ra.count AS total_count_ranking,
                ra.time AS time
            FROM
                (
                    SELECT
                        count(*) as count,
                        category,
                        time
                    FROM
                        ranking
                    WHERE
                        {$where}
                    GROUP BY
                        time,
                        category
                ) AS ra
                LEFT JOIN (
                    SELECT
                        count(*) as count,
                        category,
                        time
                    FROM
                        rising
                    WHERE
                        {$where}
                    GROUP BY
                        time,
                        category
                ) AS ri ON ra.category = ri.category
                AND ra.time = ri.time";

        return RankingPositionDB::fetchAll($query);
    }

    public function delete(\DateTime $dateTime): void
    {
        // 指定の日時より以前
        $time = new \DateTime($dateTime->format('Y-m-d H:i:s'));
        $time->modify('-1second');
        $timeStr = $time->format('Y-m-d H:i:s');

        // 適当な過去の日時
        $time2 = new \DateTime($dateTime->format('Y-m-d H:i:s'));
        $time2->modify('-1week');
        $timeStr2 = $time2->format('Y-m-d H:i:s');

        RankingPositionDB::execute("DELETE FROM ranking WHERE time between '{$timeStr2}' AND '{$timeStr}'");
        RankingPositionDB::execute("DELETE FROM rising WHERE time between '{$timeStr2}' AND '{$timeStr}'");
        RankingPositionDB::execute("DELETE FROM total_count WHERE time between '{$timeStr2}' AND '{$timeStr}'");
        RankingPositionDB::execute("DELETE FROM member WHERE time between '{$timeStr2}' AND '{$timeStr}'");
    }

    /**
     * @return array{total_count_all_category_rising:int, total_count_all_category_ranking:int}
     */
    public function insertTotalCount(string $fileTime): array
    {
        $totalCount = $this->getTotalCount(new \DateTime($fileTime), false);

        $total_count_all_category_rising = 0;
        $total_count_all_category_ranking = 0;

        foreach ($totalCount as $row) {
            $total_count_all_category_rising += (int)($row['total_count_rising'] ?? 0);
            $total_count_all_category_ranking += (int)($row['total_count_ranking'] ?? 0);
        }

        $this->inserter->import(RankingPositionDB::connect(), 'total_count', $totalCount);

        return [
            'total_count_all_category_rising' => $total_count_all_category_rising,
            'total_count_all_category_ranking' => $total_count_all_category_ranking,
        ];
    }

    public function getLastHour(int $offset = 0): string|false
    {
        $categories = AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot];
        if (AppConfig::$isStaging) {
            $categories = \App\Services\Cron\Utility\CronUtility::filterCategoriesForStaging($categories);
        }

        $categoryCount = count($categories);

        return RankingPositionDB::fetchColumn(
            "SELECT
                time
            FROM
                total_count
            GROUP BY
                time
            HAVING
                count(time) = :categoryCount
            ORDER BY
                time DESC
            LIMIT
                :offset, 1",
            compact('categoryCount', 'offset')
        );
    }
}

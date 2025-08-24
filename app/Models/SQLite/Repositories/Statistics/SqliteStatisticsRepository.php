<?php

declare(strict_types=1);

namespace App\Models\SQLite\Repositories\Statistics;

use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;
use App\Models\SQLite\SQLiteInsertImporter;
use App\Models\SQLite\SQLiteStatistics;
use App\Services\OpenChat\Dto\OpenChatDto;

class SqliteStatisticsRepository implements StatisticsRepositoryInterface
{
    public function addNewOpenChatStatisticsFromDto(OpenChatDto $dto): void
    {
        SQLiteStatistics::execute(
            "INSERT INTO
                statistics (open_chat_id, member, date)
            VALUES
                (:open_chat_id, :member, :date)",
            $dto->getStatisticsParams()
        );
    }

    public function insertDailyStatistics(int $open_chat_id, int $member, string $date): void
    {
        $query =
            'INSERT OR IGNORE INTO statistics (open_chat_id, member, date)
            VALUES
                (:open_chat_id, :member, :date)';

        SQLiteStatistics::execute($query, compact('open_chat_id', 'member', 'date'));
    }

    public function deleteDailyStatistics(int $open_chat_id): void
    {
        SQLiteStatistics::execute(
            'DELETE FROM statistics WHERE open_chat_id = :open_chat_id',
            compact('open_chat_id')
        );
    }

    public function getHourMemberChangeWithinLastWeekArray(string $date): array
    {
        // 変動がある部屋
        $query =
            "SELECT
                open_chat_id
            FROM
                statistics
            WHERE
                `date` BETWEEN DATE(:curDate, '-8 days')
                AND :curDate
            GROUP BY
                open_chat_id
            HAVING
                0 < (
                    CASE
                        WHEN COUNT(DISTINCT member) > 1 THEN 1
                        ELSE 0
                    END
                )";

        // レコード数が8以下の部屋
        $query2 =
            "SELECT
                open_chat_id
            FROM
                statistics
            GROUP BY
                open_chat_id
            HAVING
                0 < (
                    CASE
                        WHEN COUNT(member) < 8 THEN 1
                        ELSE 0
                    END
                )";

        $mode = [\PDO::FETCH_COLUMN, 0];
        $param = ['curDate' => $date];
        return array_unique(array_merge(
            SQLiteStatistics::fetchAll($query, $param, $mode),
            SQLiteStatistics::fetchAll($query2, null, $mode),
        ));
    }


    public function getMemberChangeWithinLastWeekCacheArray(string $date): array
    {
        // 変動がある部屋
        $query =
            "SELECT
                open_chat_id
            FROM
                statistics
            WHERE
                `date` BETWEEN DATE(:curDate, '-8 days')
                AND :curDate
            GROUP BY
                open_chat_id
            HAVING
                0 < (
                    CASE
                        WHEN COUNT(DISTINCT member) > 1 THEN 1
                        ELSE 0
                    END
                )";

        // レコード数が8以下の部屋
        $query2 =
            "SELECT
                open_chat_id
            FROM
                statistics
            GROUP BY
                open_chat_id
            HAVING
                0 < (
                    CASE
                        WHEN COUNT(member) < 8 THEN 1
                        ELSE 0
                    END
                )";

        // 最後のレコードが1週間以上前の部屋
        $query3 =
            "SELECT
                open_chat_id
            FROM
                statistics
            GROUP BY
                open_chat_id
            HAVING
                0 < (
                    CASE
                        WHEN MAX(`date`) <= DATE(:curDate, '-7 days') THEN 1
                        ELSE 0
                    END
                )";

        $mode = [\PDO::FETCH_COLUMN, 0];
        $param = ['curDate' => $date];
        return array_unique(array_merge(
            SQLiteStatistics::fetchAll($query, $param, $mode),
            SQLiteStatistics::fetchAll($query2, null, $mode),
            SQLiteStatistics::fetchAll($query3, $param, $mode),
        ));
    }

    public function insertMember(array $data): int
    {
        /**
         * @var SQLiteInsertImporter $inserter
         */
        $inserter = app(SQLiteInsertImporter::class);

        return $inserter->import(SQLiteStatistics::connect(), 'statistics', $data, 500);
    }

    public function getOpenChatIdArrayByDate(string $date): array
    {
        $query =
            "SELECT
                open_chat_id
            FROM
                statistics
            WHERE
                date = '{$date}'";

        return SQLiteStatistics::fetchAll($query, null, [\PDO::FETCH_COLUMN, 0]);
    }

    public function getMemberCount(int $open_chat_id, string $date): int|false
    {
        $query =
            "SELECT
                member
            FROM
                statistics
            WHERE
                open_chat_id = {$open_chat_id}
                AND date = '{$date}'";

        return SQLiteStatistics::fetchColumn($query);
    }
}

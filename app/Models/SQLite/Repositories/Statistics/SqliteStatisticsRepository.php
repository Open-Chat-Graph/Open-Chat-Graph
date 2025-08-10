<?php

declare(strict_types=1);

namespace App\Models\SQLite\Repositories\Statistics;

use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;
use App\Models\SQLite\SQLiteInsertImporter;
use App\Models\SQLite\SQLiteStatistics;
use App\Services\OpenChat\Dto\OpenChatDto;

class SqliteStatisticsRepository implements StatisticsRepositoryInterface
{
    private const MAX_RETRIES = 5;
    private const GET_DAILY_POSITION_USLEEP_TIME = 100000; // 0.1 seconds

    private function executeWithRetry(string $query, ?array $params = null): void
    {
        $attempts = 0;
        $result = false;
        $lastException = null;
        
        while ($attempts < self::MAX_RETRIES && !$result) {
            try {
                SQLiteStatistics::execute($query, $params);
                $result = true;
            } catch (\PDOException $e) {
                if (strpos($e->getMessage(), 'database is locked') === false) {
                    throw $e;
                }

                $lastException = $e;
                usleep(self::GET_DAILY_POSITION_USLEEP_TIME); // Wait for 0.1 seconds
                $attempts++;
            }
        }

        if (!$result) {
            throw $lastException ?? new \RuntimeException('Failed to execute query due to unknown error');
        }
    }

    public function addNewOpenChatStatisticsFromDto(OpenChatDto $dto): void
    {
        $this->executeWithRetry(
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

        $this->executeWithRetry($query, compact('open_chat_id', 'member', 'date'));
    }

    public function deleteDailyStatistics(int $open_chat_id): void
    {
        $this->executeWithRetry(
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
}

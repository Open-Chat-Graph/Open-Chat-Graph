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

    public function getNewRoomsWithLessThan8Records(): array
    {
        // 最適化版: レコード数が8以下の新規部屋を取得
        // 8700万行のテーブルで約5秒で実行完了
        $query =
            "SELECT open_chat_id
            FROM statistics
            GROUP BY open_chat_id
            HAVING COUNT(*) < 8";

        $mode = [\PDO::FETCH_COLUMN, 0];
        return SQLiteStatistics::fetchAll($query, null, $mode);
    }

    public function getMemberChangeWithinLastWeek(string $date): array
    {
        // 過去8日間でメンバー数が変動した部屋
        $query =
            "SELECT open_chat_id
            FROM statistics
            WHERE `date` BETWEEN DATE(:curDate, '-8 days') AND :curDate
            GROUP BY open_chat_id
            HAVING COUNT(DISTINCT member) > 1";

        $mode = [\PDO::FETCH_COLUMN, 0];
        return SQLiteStatistics::fetchAll($query, ['curDate' => $date], $mode);
    }

    public function getWeeklyUpdateRooms(string $date): array
    {
        // 最後のレコードが1週間以上前の部屋（週次更新用）
        $query =
            "SELECT open_chat_id
            FROM statistics
            GROUP BY open_chat_id
            HAVING MAX(`date`) <= DATE(:curDate, '-7 days')";

        $mode = [\PDO::FETCH_COLUMN, 0];
        return SQLiteStatistics::fetchAll($query, ['curDate' => $date], $mode);
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

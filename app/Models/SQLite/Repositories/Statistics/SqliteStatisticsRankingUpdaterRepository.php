<?php

declare(strict_types=1);

namespace App\Models\SQLite\Repositories\Statistics;

use App\Models\ExecuteSqlFile;
use App\Models\Importer\SqlInsert;
use App\Models\Repositories\Statistics\StatisticsRankingUpdaterRepositoryInterface;
use App\Models\SQLite\SQLiteStatistics;
use App\Models\Repositories\DB;
use Shadow\DBInterface;

class SqliteStatisticsRankingUpdaterRepository implements StatisticsRankingUpdaterRepositoryInterface
{
    protected const DAILY_RANKING_SQL = __DIR__ . '/sql/SQLite_StatisticsRankingUpdaterRepository_updateCreateDailyRankingTable.sql';
    protected const PAST_WEEK_RANKING_SQL = __DIR__ . '/sql/SQLite_StatisticsRankingUpdaterRepository_updateCreatePastWeekRankingTable.sql';
    protected const DATE_PLACE_HOLDER = ':DATE_STRING';

    public function __construct(
        private ExecuteSqlFile $execSql,
        private SqlInsert $inserter
    ) {
    }

    public function updateCreateDailyRankingTable(string $date)
    {
        $result = SQLiteStatistics::fetchAll(
            file_get_contents(self::DAILY_RANKING_SQL),
            [self::DATE_PLACE_HOLDER => $date]
        );

        $this->exportProcess('statistics_ranking_day', new DB, ...$this->buildData($result));
    }

    public function updateCreatePastWeekRankingTable(string $date)
    {
        $result = SQLiteStatistics::fetchAll(
            file_get_contents(self::PAST_WEEK_RANKING_SQL),
            [self::DATE_PLACE_HOLDER => $date]
        );

        $this->exportProcess('statistics_ranking_week', new DB, ...$this->buildData($result));
    }

    /**
     * @param array{ open_chat_id: int, diff_member: int, percent_increase: float }[] $result 
     */
    private function buildData(array $result)
    {
        $keys = ['id', 'open_chat_id', 'diff_member', 'percent_increase'];
        $data = [];
        foreach ($result as $key => $row) {
            $data[] = [$key + 1, $row['open_chat_id'], $row['diff_member'], $row['percent_increase']];
        }

        return [$keys, $data];
    }

    private function exportProcess(string $tableName, DBInterface $db, array $keys, array $data): void
    {
        $db->execute("TRUNCATE TABLE {$tableName}");
        $this->inserter->importWithKeys($db->connect(), $tableName, $keys, $data);
    }
}

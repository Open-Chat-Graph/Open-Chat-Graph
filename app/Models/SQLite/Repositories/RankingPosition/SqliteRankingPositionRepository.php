<?php

declare(strict_types=1);

namespace App\Models\SQLite\Repositories\RankingPosition;

use App\Config\AppConfig;
use App\Models\Repositories\RankingPosition\RankingPositionRepositoryInterface;
use App\Models\SQLite\SQLiteInsertImporter;
use App\Models\SQLite\SQLiteRankingPosition;
use App\Services\Cron\Utility\CronUtility;
use Shared\MimimalCmsConfig;

class SqliteRankingPositionRepository implements RankingPositionRepositoryInterface
{
    function __construct(
        private SQLiteInsertImporter $inserter
    ) {
    }

    public function insertDailyRankingPosition(array $rankingHourArray, string $date): int
    {
        $dateColumn = compact('date');
        $data = array_map(fn ($row) => $row + $dateColumn, $rankingHourArray);
        return $this->inserter->import(SQLiteRankingPosition::connect(), 'ranking', $data, 500);
    }

    public function insertDailyRisingPosition(array $risingHourArray, string $date): int
    {
        $dateColumn = compact('date');
        $data = array_map(fn ($row) => $row + $dateColumn, $risingHourArray);
        return $this->inserter->import(SQLiteRankingPosition::connect(), 'rising', $data, 500);
    }

    public function insertTotalCount(array $totalCount): int
    {
        return $this->inserter->import(SQLiteRankingPosition::connect(), 'total_count', $totalCount, 500);
    }

    public function deleteDailyPosition(int $open_chat_id): void
    {
        SQLiteRankingPosition::execute(
            "DELETE FROM
                rising
            WHERE
                open_chat_id = :open_chat_id",
            compact('open_chat_id')
        );

        SQLiteRankingPosition::execute(
            "DELETE FROM
                ranking
            WHERE
                open_chat_id = :open_chat_id",
            compact('open_chat_id')
        );
    }

    public function getLastDate(): string|false
    {
        $categories = AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot];
        if (AppConfig::$isStaging) {
            $categories = CronUtility::filterCategoriesForStaging($categories);
        }
        
        $categoryCount = count($categories);

        return SQLiteRankingPosition::fetchColumn(
            "SELECT
                DATE(time)
            FROM
                total_count
            GROUP BY
                time
            HAVING
                count(time) = :categoryCount
            ORDER BY
                time DESC
            LIMIT
                1",
            compact('categoryCount')
        );
    }
}

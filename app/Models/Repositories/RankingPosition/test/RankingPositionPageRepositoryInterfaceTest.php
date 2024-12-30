<?php

declare(strict_types=1);

use App\Models\SQLite\Repositories\Statistics\SqliteStatisticsRankingUpdaterRepository;
use PHPUnit\Framework\TestCase;

class SqliteStatisticsRankingUpdaterRepositoryTest extends TestCase
{
    private SqliteStatisticsRankingUpdaterRepository $instance;

    public function test()
    {
        $this->instance = app(SqliteStatisticsRankingUpdaterRepository::class);

        $result = $this->instance->getDailyRankingPositionTimeAsc(192, 8);
        debug($result);
        $this->assertTrue(true);
    }
}

<?php

declare(strict_types=1);

use App\Models\SQLite\Repositories\RankingPosition\SqliteHourMemberRankingUpdaterRepository;
use PHPUnit\Framework\TestCase;

class SqliteHourMemberRankingUpdaterRepositoryTest extends TestCase
{
    private SqliteHourMemberRankingUpdaterRepository $instance;

    public function test()
    {
        $this->instance = app(SqliteHourMemberRankingUpdaterRepository::class);

        $result = $this->instance->buildRankingData(new \DateTime('2024-02-17 05:30:00'));

        debug($result[0]);

        $this->assertTrue(true);
    }
}

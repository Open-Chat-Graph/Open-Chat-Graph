<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/RankingPosition/Persistence/test/RankingPositionDailyPersistenceTest.php
 */

declare(strict_types=1);

use App\Services\RankingPosition\Persistence\RankingPositionDailyPersistence;
use PHPUnit\Framework\TestCase;

class RankingPositionDailyPersistenceTest extends TestCase
{
    public RankingPositionDailyPersistence $instance;

    public function test()
    {
        $this->instance = app(RankingPositionDailyPersistence::class);

        $this->instance->persistHourToDaily();

        $this->assertEquals(0, 0);
    }
}

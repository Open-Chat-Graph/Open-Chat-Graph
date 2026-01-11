<?php

declare(strict_types=1);

use App\Services\RankingPosition\Persistence\RankingPositionHourPersistence;
use PHPUnit\Framework\TestCase;

// docker compose exec app ./vendor/bin/phpunit app/Services/RankingPosition/Persistence/test/RankingPositionHourPersistenceTest.php
class RankingPositionHourPersistenceTest extends TestCase
{
    public RankingPositionHourPersistence $instance;

    public function test()
    {
        $this->instance = app(RankingPositionHourPersistence::class);

        $this->instance->persistAllCategoriesBackground();

        $this->assertEquals(0, 0);
    }
}

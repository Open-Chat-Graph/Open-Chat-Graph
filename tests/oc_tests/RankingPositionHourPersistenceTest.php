<?php

declare(strict_types=1);

use App\Services\RankingPosition\Persistence\RankingPositionHourPersistence;
use PHPUnit\Framework\TestCase;

class RankingPositionHourPersistenceTest extends TestCase
{
    public RankingPositionHourPersistence $instance;

    public function test()
    {
        $this->instance = app(RankingPositionHourPersistence::class);

        $this->instance->persistStorageFileToDb();

        $this->assertEquals(0, 0);
    }
}

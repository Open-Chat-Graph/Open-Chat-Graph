<?php

use App\Config\AppConfig;
use App\Models\SQLite\Repositories\RankingPosition\SqliteRankingPositionHourRepository;
use PHPUnit\Framework\TestCase;
use App\Services\RankingPosition\Store\RisingPositionStore;

class SqliteRankingPositionHourRepositoryTest extends TestCase
{
    public function testinsertFromDtoArray()
    {
        /**
         * @var RisingPositionStore $test
         */
        $test = app(RisingPositionStore::class);

        /**
         * @var SqliteRankingPositionHourRepository $repo
         */
        $repo = app(SqliteRankingPositionHourRepository::class);

        foreach (AppConfig::$OPEN_CHAT_CATEGORY as $category) {
            $result = $repo->insertRisingHourFromDtoArray(...$test->getStorageData((string)$category));
            debug($result);
        }

        $this->assertTrue(true);
    }

    public function testgetMinRankingHour()
    {
        /**
         * @var SqliteRankingPositionHourRepository $repo
         */
        $repo = app(SqliteRankingPositionHourRepository::class);

        $result = $repo->getDailyRising(new \DateTime());

        debug(array_slice($result, 0, 10));

        $this->assertTrue(true);
    }

    public function testgetTotalCount()
    {
        /**
         * @var SqliteRankingPositionHourRepository $repo
         */
        $repo = app(SqliteRankingPositionHourRepository::class);

        $result = $repo->getTotalCount(new \DateTime());

        debug($result);

        $this->assertTrue(true);
    }
}

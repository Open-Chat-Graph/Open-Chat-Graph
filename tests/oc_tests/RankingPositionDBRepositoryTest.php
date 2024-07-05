<?php

use App\Models\RankingPositionDB\Repositories\RankingPositionHourRepository;
use PHPUnit\Framework\TestCase;

class RankingPositionDBRepositoryTest extends TestCase
{
    public function testinsertDailyRankingPosition()
    {
        /**
         * @var RankingPositionHourRepository $repo
         */
        $repo = app(RankingPositionHourRepository::class);

        $result = $repo->dalete(new DateTime('2024-02-17 07:30:00'));

        debug($result);

        $this->assertTrue(true);
    }
}

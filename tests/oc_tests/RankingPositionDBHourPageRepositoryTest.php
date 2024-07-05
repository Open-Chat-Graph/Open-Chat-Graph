<?php

use App\Models\RankingPositionDB\Repositories\RankingPositionHourPageRepository;
use App\Services\OpenChat\Enum\RankingType;
use PHPUnit\Framework\TestCase;

class RankingPositionDBHourPageRepositoryTest extends TestCase
{
    public function testinsertDailyRankingPosition()
    {
        /**
         * @var RankingPositionHourPageRepository $repo
         */
        $repo = app(RankingPositionHourPageRepository::class);

        $result = $repo->getHourPosition(RankingType::Ranking, 129458, 0, 24, new DateTime('2024-02-17 09:30:00'));

        debug($result);

        $this->assertTrue(true);
    }
}

<?php

use App\Models\RankingPositionDB\Repositories\HourMemberRankingUpdaterRepository;
use App\Services\OpenChat\Enum\RankingType;
use PHPUnit\Framework\TestCase;

class HourMemberRankingUpdaterRepositoryTest extends TestCase
{
    public function testinsertDailyRankingPosition()
    {
        /**
         * @var HourMemberRankingUpdaterRepository $repo
         */
        $repo = app(HourMemberRankingUpdaterRepository::class);

        $result = $repo->getHourRanking(new DateTime('2024-02-18 09:30:00'));

        debug($result[0]);

        $this->assertTrue(true);
    }
}

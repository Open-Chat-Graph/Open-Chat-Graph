<?php

use App\Models\RankingPositionDB\Repositories\RankingPositionHourPageRepository;
use App\Services\OpenChat\Enum\RankingType;
use PHPUnit\Framework\TestCase;

class RankingPositionHourPageRepositoryTest extends TestCase
{
    public function testgetHourPosition()
    {
        /**
         * @var RankingPositionHourPageRepository $repo
         */
        $repo = app(RankingPositionHourPageRepository::class);

        $result = $repo->getHourPosition(RankingType::Ranking, 129458, 0, 24, new DateTime('2024-09-04 09:30:00'));

        debug($result);

        $this->assertTrue(true);
    }
}

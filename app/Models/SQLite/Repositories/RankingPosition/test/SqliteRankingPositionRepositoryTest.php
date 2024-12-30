<?php

use App\Models\Repositories\RankingPosition\RankingPositionRepositoryInterface;
use PHPUnit\Framework\TestCase;

class SqliteRankingPositionRepositoryTest extends TestCase
{
    public function testinsertDailyRankingPosition()
    {
        /**
         * @var RankingPositionRepositoryInterface $repo
         */
        $repo = app(RankingPositionRepositoryInterface::class);

        $result = $repo->insertDailyRankingPosition();

        debug($result);

        $this->assertTrue(true);
    }
}

<?php

declare(strict_types=1);

use App\Models\RecommendRepositories\OfficialRoomRankingRepository;
use PHPUnit\Framework\TestCase;

class OfficialRoomRankingRepositoryTest extends TestCase
{
    private OfficialRoomRankingRepository $inst;

    public function test()
    {
        $this->inst = app(OfficialRoomRankingRepository::class);

        $r = $this->inst->getRanking('1', 'statistics_ranking_day', 10, 10);
        debug($r);

        $this->assertTrue(true);
    }
}

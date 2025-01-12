<?php

declare(strict_types=1);

use App\Models\RecommendRepositories\RecommendRankingRepository;
use PHPUnit\Framework\TestCase;

class RecommendRankingRepositoryTest extends TestCase
{
    private RecommendRankingRepository $inst;

    public function getListOrderByMemberDesc_test()
    {
        $this->inst = app(RecommendRankingRepository::class);

        $r = $this->inst->getListOrderByMemberDesc('オプチャ サポート', [], 10);
        debug($r);

        $this->assertTrue(true);
    }
}

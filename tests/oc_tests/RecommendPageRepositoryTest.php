<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Models\RecommendRepositories\RecommendPageRepository;
use PHPUnit\Framework\TestCase;

class RecommendPageRepositoryTest extends TestCase
{
    private RecommendPageRepository $inst;

    public function test()
    {
        $this->inst = app(RecommendPageRepository::class);

        $r = $this->inst->getListOrderByMemberDesc(0, 'ボイメで歌', [], 1);
        debug($r);

        $this->assertTrue(true);
    }
}

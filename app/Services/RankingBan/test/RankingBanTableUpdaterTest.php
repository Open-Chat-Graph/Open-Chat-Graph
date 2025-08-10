<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Services\RankingBan\RankingBanTableUpdater;
use PHPUnit\Framework\TestCase;

class RankingBanTableUpdaterTest extends TestCase
{
    private RankingBanTableUpdater $inst;
    public function test()
    {
        $this->inst = app(RankingBanTableUpdater::class);

        AppConfig::$isDevlopment = false;
        $this->inst->updateRankingBanTable();

        $this->assertTrue(true);
    }
}

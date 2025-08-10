<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Services\RankingBan\RankingBanTableUpdater;
use PHPUnit\Framework\TestCase;

class RankingBanTableUpdaterTest extends TestCase
{
    private RankingBanTableUpdater $inst;
    public function test()
    {
        $this->inst = app(RankingBanTableUpdater::class, ['time' => new \DateTime('2023-01-31 16:30:00')]);

        AppConfig::$isDevlopment = false;
        $this->inst->updateRankingBanTable(OpenChatServicesUtility::getModifiedCronTime('now'));

        $this->assertTrue(true);
    }
}

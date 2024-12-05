<?php

declare(strict_types=1);

use App\Services\RankingPosition\RankingPositionDailyUpdater;
use PHPUnit\Framework\TestCase;
use App\Models\Repositories\DB;

class RankingPositionDailyUpdaterTest extends TestCase
{
    private RankingPositionDailyUpdater $inst;
    function test()
    {
        $this->inst = app(RankingPositionDailyUpdater::class);
        $this->inst->updateYesterdayDailyDb();

        $this->assertIsBool(true);
    }
    
}

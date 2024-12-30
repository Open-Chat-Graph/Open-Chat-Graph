<?php

declare(strict_types=1);

use App\Services\StaticData\StaticDataGenerator;
use App\Services\UpdateHourlyMemberRankingService;

use PHPUnit\Framework\TestCase;

class UpdateHourlyMemberRankingServiceTest extends TestCase
{
    private UpdateHourlyMemberRankingService $inst;
    private StaticDataGenerator $inst2;

    public function test()
    {
        $this->inst2 = app(StaticDataGenerator::class);
        $this->inst = app(UpdateHourlyMemberRankingService::class);

        $this->inst->update();

        $this->assertTrue(true);
    } 
}

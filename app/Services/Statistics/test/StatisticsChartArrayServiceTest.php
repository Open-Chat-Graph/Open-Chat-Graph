<?php

declare(strict_types=1);

use App\Services\Statistics\StatisticsChartArrayService;
use PHPUnit\Framework\TestCase;

class StatisticsChartArrayServiceTest extends TestCase
{
    private StatisticsChartArrayService $instance;

    public function test()
    {
        $this->instance = app(StatisticsChartArrayService::class);

        $result = $this->instance->buildStatisticsChartArray(192);
        debug($result);
        $this->assertTrue(true);
    }
}

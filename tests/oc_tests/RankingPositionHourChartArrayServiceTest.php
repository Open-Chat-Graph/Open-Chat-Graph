<?php

declare(strict_types=1);

use App\Services\RankingPosition\RankingPositionHourChartArrayService;
use PHPUnit\Framework\TestCase;

class RankingPositionHourChartArrayServiceTest extends TestCase
{
    private RankingPositionHourChartArrayService $instance;

    public function test()
    {
        $this->instance = app(RankingPositionHourChartArrayService::class);

        $result = $this->instance->getRankingPositionHourChartArray('F1_Ziqfzj3Rs-NkBze8caN9qD-Vm9Iir4QnKu4xJk8Di94hCClypR6s5yio', 0);
        debug(json_encode($result));
        $this->assertTrue(true);
    }
}

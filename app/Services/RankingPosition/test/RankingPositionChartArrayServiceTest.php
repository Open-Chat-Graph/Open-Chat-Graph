<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/RankingPosition/test/RankingPositionChartArrayServiceTest.php
 */

declare(strict_types=1);

use App\Services\RankingPosition\RankingPositionChartArrayService;
use PHPUnit\Framework\TestCase;

class RankingPositionChartArrayServiceTest extends TestCase
{
    private RankingPositionChartArrayService $instance;

    public function test()
    {
        $this->instance = app(RankingPositionChartArrayService::class);

        $result = $this->instance->getRankingPositionChartArray(192, 8);
        debug(json_encode($result));
        $this->assertTrue(true);
    }
}

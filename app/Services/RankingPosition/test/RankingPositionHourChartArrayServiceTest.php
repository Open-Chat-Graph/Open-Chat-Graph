<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/RankingPosition/test/RankingPositionHourChartArrayServiceTest.php
 */

declare(strict_types=1);

use App\Services\OpenChat\Enum\RankingType;
use App\Services\RankingPosition\RankingPositionHourChartArrayService;
use PHPUnit\Framework\TestCase;

class RankingPositionHourChartArrayServiceTest extends TestCase
{
    private RankingPositionHourChartArrayService $instance;

    public function test()
    {
        $this->instance = app(RankingPositionHourChartArrayService::class);

        $result = $this->instance->getPositionHourChartArray(RankingType::Ranking, 192, 0);
        debug(json_encode($result));

        $this->assertSame(count($result->date), count($result->member));
        $this->assertSame(count($result->date), count($result->position));
    }
}

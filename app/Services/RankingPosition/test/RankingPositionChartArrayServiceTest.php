<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/RankingPosition/test/RankingPositionChartArrayServiceTest.php
 */

declare(strict_types=1);

use App\Services\OpenChat\Enum\RankingType;
use App\Services\RankingPosition\RankingPositionChartArrayService;
use PHPUnit\Framework\TestCase;

class RankingPositionChartArrayServiceTest extends TestCase
{
    private RankingPositionChartArrayService $instance;

    public function test()
    {
        $this->instance = app(RankingPositionChartArrayService::class);

        $result = $this->instance->getRankingPositionChartArray(
            RankingType::Ranking,
            192,
            8,
            new \DateTime('-1 month'),
            new \DateTime('now')
        );
        debug(json_encode($result));

        $this->assertSame(count($result->time), count($result->position));
        $this->assertSame(count($result->time), count($result->totalCount));
    }
}

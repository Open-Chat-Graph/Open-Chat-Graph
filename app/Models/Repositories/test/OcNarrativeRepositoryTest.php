<?php

/**
 * OcNarrativeRepository のテスト
 *
 * 実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Models/Repositories/test/OcNarrativeRepositoryTest.php
 *
 * 内容:
 * - 内部で StatisticsOhlcRepositoryInterface / RankingPositionOhlcRepositoryInterface に
 *   正しい引数で委譲することを mock で検証
 * - 戻り値の透過性 (受け取った配列をそのまま返す)
 */

declare(strict_types=1);

use App\Models\Repositories\OcNarrativeRepository;
use App\Models\Repositories\OcNarrativeRepositoryInterface;
use App\Models\Repositories\RankingPosition\RankingPositionOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsOhlcRepositoryInterface;
use App\Services\OpenChat\Enum\RankingType;
use PHPUnit\Framework\TestCase;

class OcNarrativeRepositoryTest extends TestCase
{
    public function test_getMemberMetrics_delegates_to_statistics_ohlc_repository(): void
    {
        $expected = [
            'curr' => 1234,
            'curr_date' => '2026-05-20',
            'm7' => 1200, 'm30' => 1000, 'm90' => 800,
            'sample_n' => 90,
            'peak_high' => 1500, 'peak_date' => '2026-05-01',
            'max_single_day_growth' => 50, 'max_growth_date' => '2026-04-15',
            'first_date' => '2025-08-01',
        ];

        $statsOhlc = $this->createMock(StatisticsOhlcRepositoryInterface::class);
        $statsOhlc->expects($this->once())
            ->method('getMemberMetricsForNarrative')
            ->with(42)
            ->willReturn($expected);

        $rankingOhlc = $this->createMock(RankingPositionOhlcRepositoryInterface::class);

        $repo = new OcNarrativeRepository($statsOhlc, $rankingOhlc);

        $this->assertSame($expected, $repo->getMemberMetrics(42));
    }

    public function test_getPositionMovement_uses_RankingType_Ranking_and_passes_args(): void
    {
        $expected = [
            'oldest_close' => 50, 'oldest_date' => '2026-04-25',
            'latest_close' => 12, 'latest_date' => '2026-05-25',
            'best_high' => 10,
            'sample_n' => 30,
        ];

        $statsOhlc = $this->createMock(StatisticsOhlcRepositoryInterface::class);

        $rankingOhlc = $this->createMock(RankingPositionOhlcRepositoryInterface::class);
        $rankingOhlc->expects($this->once())
            ->method('getRecentPositionMovement')
            ->with(
                $this->equalTo(99),
                $this->equalTo(3),
                $this->equalTo(RankingType::Ranking),
                $this->equalTo(30),
            )
            ->willReturn($expected);

        $repo = new OcNarrativeRepository($statsOhlc, $rankingOhlc);

        $this->assertSame($expected, $repo->getPositionMovement(99, 3));
    }

    public function test_getPositionMovement_with_custom_days(): void
    {
        $statsOhlc = $this->createMock(StatisticsOhlcRepositoryInterface::class);
        $rankingOhlc = $this->createMock(RankingPositionOhlcRepositoryInterface::class);
        $rankingOhlc->expects($this->once())
            ->method('getRecentPositionMovement')
            ->with(
                $this->equalTo(1),
                $this->equalTo(0),
                $this->equalTo(RankingType::Ranking),
                $this->equalTo(7),
            )
            ->willReturn(['oldest_close' => null, 'oldest_date' => null, 'latest_close' => null, 'latest_date' => null, 'best_high' => null, 'sample_n' => 0]);

        $repo = new OcNarrativeRepository($statsOhlc, $rankingOhlc);
        $repo->getPositionMovement(1, 0, 7);
    }

    public function test_implements_interface(): void
    {
        $repo = new OcNarrativeRepository(
            $this->createMock(StatisticsOhlcRepositoryInterface::class),
            $this->createMock(RankingPositionOhlcRepositoryInterface::class),
        );
        $this->assertInstanceOf(OcNarrativeRepositoryInterface::class, $repo);
    }
}

<?php

/**
 * ChartAvailabilityCalculatorのテスト
 *
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Statistics/ChartMeta/test/ChartAvailabilityCalculatorTest.php
 *
 * 可用性メタのしきい値判定が境界で正しいことを確認する。
 */

declare(strict_types=1);

use App\Services\Statistics\ChartMeta\ChartAvailabilityCalculator;
use PHPUnit\Framework\TestCase;

class ChartAvailabilityCalculatorTest extends TestCase
{
    public function testDailyOhlcAllZeroIsAllFalse(): void
    {
        $result = ChartAvailabilityCalculator::dailyOhlc(
            ['all_count' => 0, 'week_count' => 0, 'month_count' => 0],
            8,
            31,
        );

        $this->assertSame(['week' => false, 'month' => false, 'all' => false], $result);
    }

    public function testDailyOhlcWeekBoundary(): void
    {
        // week_count == weekWindow → true（>= 判定の境界）
        $eq = ChartAvailabilityCalculator::dailyOhlc(
            ['all_count' => 10, 'week_count' => 8, 'month_count' => 0],
            8,
            31,
        );
        $this->assertTrue($eq['week'], 'week_count == weekWindow は有効');
        $this->assertTrue($eq['all'], 'all_count>0 なら all は常に true');

        // week_count == weekWindow - 1 → false
        $below = ChartAvailabilityCalculator::dailyOhlc(
            ['all_count' => 10, 'week_count' => 7, 'month_count' => 0],
            8,
            31,
        );
        $this->assertFalse($below['week'], 'week_count < weekWindow は無効');
    }

    public function testDailyOhlcMonthBoundary(): void
    {
        // month_count * 2 == monthWindow → true（半分以上の境界。31/2 → month_count=16 で 32>=31）
        $eq = ChartAvailabilityCalculator::dailyOhlc(
            ['all_count' => 20, 'week_count' => 0, 'month_count' => 16],
            8,
            31,
        );
        $this->assertTrue($eq['month'], 'month_count*2 >= monthWindow は有効');

        // ちょうど等しいケース（偶数ウィンドウで month_count*2 == monthWindow）
        $exact = ChartAvailabilityCalculator::dailyOhlc(
            ['all_count' => 20, 'week_count' => 0, 'month_count' => 15],
            8,
            30,
        );
        $this->assertTrue($exact['month'], 'month_count*2 == monthWindow ちょうどは有効');

        // 半分未満 → false
        $below = ChartAvailabilityCalculator::dailyOhlc(
            ['all_count' => 20, 'week_count' => 0, 'month_count' => 14],
            8,
            30,
        );
        $this->assertFalse($below['month'], 'month_count*2 < monthWindow は無効');
    }

    public function testDailyPosition(): void
    {
        $counts = [
            'ranking_in' => ['week' => 0, 'month' => 3, 'all' => 5],
            'ranking_all' => ['week' => 1, 'month' => 0, 'all' => 0],
            'rising_in' => ['week' => 0, 'month' => 0, 'all' => 2],
            'rising_all' => ['week' => 0, 'month' => 0, 'all' => 0],
        ];

        $result = ChartAvailabilityCalculator::dailyPosition($counts);

        $this->assertSame(
            ['ranking_in' => false, 'ranking_all' => true, 'rising_in' => false, 'rising_all' => false],
            $result['week']
        );
        $this->assertSame(
            ['ranking_in' => true, 'ranking_all' => false, 'rising_in' => false, 'rising_all' => false],
            $result['month']
        );
        $this->assertSame(
            ['ranking_in' => true, 'ranking_all' => false, 'rising_in' => true, 'rising_all' => false],
            $result['all']
        );
    }

    public function testDailyPositionZeroIsFalse(): void
    {
        $zero = ['week' => 0, 'month' => 0, 'all' => 0];
        $counts = [
            'ranking_in' => $zero,
            'ranking_all' => $zero,
            'rising_in' => $zero,
            'rising_all' => $zero,
        ];

        $result = ChartAvailabilityCalculator::dailyPosition($counts);

        foreach (['week', 'month', 'all'] as $period) {
            $this->assertSame(
                ['ranking_in' => false, 'ranking_all' => false, 'rising_in' => false, 'rising_all' => false],
                $result[$period],
                "count 0 の {$period} は全false（> 0 判定）"
            );
        }
    }
}

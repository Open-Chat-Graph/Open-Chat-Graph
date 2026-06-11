<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Chart/test/OpenChatChartApiServiceTest.php
 */

declare(strict_types=1);

use App\Services\Chart\OpenChatChartApiService;
use PHPUnit\Framework\TestCase;

class OpenChatChartApiServiceTest extends TestCase
{
    private OpenChatChartApiService $instance;

    protected function setUp(): void
    {
        $this->instance = app(OpenChatChartApiService::class);
    }

    public function testHourWithMeta()
    {
        $result = $this->instance->buildChartResponse(192, 8, 'hour', 'none', 'all', 'line', true);

        // hourビューは毎時系列のみ（日次メンバー配列を含まない）+ メタデータ
        $this->assertSame(count($result['date']), count($result['member']));
        $this->assertSame([], $result['position']);
        $this->assertArrayHasKey('hourAvailability', $result['meta']);
        $this->assertArrayHasKey('positionAvailability', $result['meta']);
        $this->assertArrayHasKey('ohlcAvailability', $result['meta']);
        $this->assertArrayNotHasKey('memberOhlc', $result);
    }

    public function testDayWithPosition()
    {
        $result = $this->instance->buildChartResponse(192, 8, 'day', 'ranking', 'in', 'line', false);

        // 日次メンバー系列と順位系列が同じ日付軸で揃って返ること
        $this->assertSame(count($result['date']), count($result['member']));
        if ($result['position']) {
            $this->assertSame(count($result['date']), count($result['position']));
        }
        $this->assertArrayNotHasKey('meta', $result);
    }

    public function testCandlestick()
    {
        $result = $this->instance->buildChartResponse(192, 8, 'day', 'ranking', 'all', 'candlestick', false);

        $this->assertSame(count($result['date']), count($result['member']));
        $this->assertArrayHasKey('memberOhlc', $result);
        $this->assertArrayHasKey('positionOhlc', $result);
    }
}

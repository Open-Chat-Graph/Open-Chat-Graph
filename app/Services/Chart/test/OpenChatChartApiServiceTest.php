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

    public function testSeriesMemberOnlyReturnsOnlyMemberLayer()
    {
        // series=member: date と member だけを返す（position/ohlc/メタを含まない）
        $result = $this->instance->buildChartResponse(192, 8, 'day', 'ranking', 'in', 'line', false, null, null, 'member');

        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('member', $result);
        $this->assertSame(count($result['date']), count($result['member']));
        $this->assertArrayNotHasKey('position', $result);
        $this->assertArrayNotHasKey('time', $result);
        $this->assertArrayNotHasKey('totalCount', $result);
        $this->assertArrayNotHasKey('memberOhlc', $result);
        $this->assertArrayNotHasKey('positionOhlc', $result);
        $this->assertArrayNotHasKey('meta', $result);
    }

    public function testSeriesPositionOnlyReturnsOnlyPositionLayer()
    {
        // series=position: date と time/position/totalCount を返す（member を含まない）
        $result = $this->instance->buildChartResponse(192, 8, 'day', 'ranking', 'in', 'line', false, null, null, 'position');

        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('position', $result);
        $this->assertArrayHasKey('time', $result);
        $this->assertArrayHasKey('totalCount', $result);
        $this->assertArrayNotHasKey('member', $result);
        $this->assertArrayNotHasKey('memberOhlc', $result);
        $this->assertArrayNotHasKey('meta', $result);
        if ($result['position']) {
            $this->assertSame(count($result['date']), count($result['position']));
        }
    }

    public function testSeriesMemberAndPositionShareSameDateAxis()
    {
        // series=member,position: 両層を1レスポンスで、共通の date 軸で返す（member は1回だけ）
        $result = $this->instance->buildChartResponse(192, 8, 'day', 'ranking', 'in', 'line', false, null, null, 'member,position');

        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('member', $result);
        $this->assertArrayHasKey('position', $result);
        $this->assertSame(count($result['date']), count($result['member']));
        if ($result['position']) {
            $this->assertSame(count($result['date']), count($result['position']));
        }
        $this->assertArrayNotHasKey('meta', $result);
    }

    public function testSeriesMemberOhlcReturnsOnlyMemberOhlcLayer()
    {
        $result = $this->instance->buildChartResponse(192, 8, 'day', 'ranking', 'all', 'candlestick', false, null, null, 'memberOhlc');

        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('memberOhlc', $result);
        $this->assertArrayNotHasKey('member', $result);
        $this->assertArrayNotHasKey('position', $result);
        $this->assertArrayNotHasKey('positionOhlc', $result);
        $this->assertArrayNotHasKey('meta', $result);
    }

    public function testSeriesPositionOhlcReturnsOnlyPositionOhlcLayer()
    {
        $result = $this->instance->buildChartResponse(192, 8, 'day', 'ranking', 'all', 'candlestick', false, null, null, 'positionOhlc');

        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('positionOhlc', $result);
        $this->assertArrayNotHasKey('member', $result);
        $this->assertArrayNotHasKey('memberOhlc', $result);
        $this->assertArrayNotHasKey('position', $result);
        $this->assertArrayNotHasKey('meta', $result);
    }

    public function testUnknownSeriesFallsBackToLegacyResponse()
    {
        // 未知の series 値だけ → 妥当な層なし → 従来レスポンスにフォールバック（series 無しと同等）
        $result = $this->instance->buildChartResponse(192, 8, 'day', 'ranking', 'in', 'line', false, null, null, 'bogus');

        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('member', $result);
        $this->assertArrayHasKey('position', $result);
        $this->assertArrayHasKey('time', $result);
        $this->assertArrayHasKey('totalCount', $result);
    }
}

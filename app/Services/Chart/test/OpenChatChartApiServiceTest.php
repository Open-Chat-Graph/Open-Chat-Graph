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
        // hour は x 軸自体が時刻なので順位の時刻ラベル time は持たない
        $this->assertArrayNotHasKey('time', $result);
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
        // ランキングは終日時刻を持たないので time 配列は返さない
        $this->assertArrayNotHasKey('time', $result);
        $this->assertArrayNotHasKey('meta', $result);
    }

    public function testCandlestick()
    {
        $result = $this->instance->buildChartResponse(192, 8, 'day', 'ranking', 'all', 'candlestick', false);

        $this->assertSame(count($result['date']), count($result['member']));

        // OHLC は共通の ohlcDate 軸＋date抜きの値配列で返る（memberOhlc/positionOhlc は ohlcDate と同長）
        $this->assertArrayHasKey('ohlcDate', $result);
        $this->assertArrayHasKey('memberOhlc', $result);
        $this->assertArrayHasKey('positionOhlc', $result);
        $this->assertSame(count($result['ohlcDate']), count($result['memberOhlc']));
        $this->assertSame(count($result['ohlcDate']), count($result['positionOhlc']));

        // ローソク足は時刻ラベル折れ線tooltipを使わないので time は持たない
        $this->assertArrayNotHasKey('time', $result);

        // 各 OHLC 要素は date を持たない（ohlcDate と重複させない）
        if ($result['memberOhlc']) {
            $this->assertArrayNotHasKey('date', $result['memberOhlc'][0]);
            $this->assertArrayHasKey('open_member', $result['memberOhlc'][0]);
        }
        foreach ($result['positionOhlc'] as $el) {
            if ($el !== null) {
                $this->assertArrayNotHasKey('date', $el);
                $this->assertArrayHasKey('open_position', $el);
                break;
            }
        }
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
        $this->assertArrayNotHasKey('ohlcDate', $result);
        $this->assertArrayNotHasKey('memberOhlc', $result);
        $this->assertArrayNotHasKey('positionOhlc', $result);
        $this->assertArrayNotHasKey('meta', $result);
    }

    public function testSeriesPositionRankingOmitsTime()
    {
        // series=position（ランキング）: date と position/totalCount を返す。
        // ランキングは時刻を持たないので time 配列は返さない（member も含まない）
        $result = $this->instance->buildChartResponse(192, 8, 'day', 'ranking', 'in', 'line', false, null, null, 'position');

        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('position', $result);
        $this->assertArrayHasKey('totalCount', $result);
        $this->assertArrayNotHasKey('time', $result);
        $this->assertArrayNotHasKey('member', $result);
        $this->assertArrayNotHasKey('memberOhlc', $result);
        $this->assertArrayNotHasKey('meta', $result);
        if ($result['position']) {
            $this->assertSame(count($result['date']), count($result['position']));
        }
    }

    public function testSeriesPositionRisingIncludesTime()
    {
        // series=position（急上昇）: 急上昇のみ時刻ラベル time を返す
        $result = $this->instance->buildChartResponse(192, 8, 'day', 'rising', 'all', 'line', false, null, null, 'position');

        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('position', $result);
        $this->assertArrayHasKey('totalCount', $result);
        $this->assertArrayHasKey('time', $result);
        $this->assertArrayNotHasKey('member', $result);
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
        $this->assertArrayHasKey('ohlcDate', $result);
        $this->assertArrayHasKey('memberOhlc', $result);
        $this->assertSame(count($result['ohlcDate']), count($result['memberOhlc']));
        $this->assertArrayNotHasKey('member', $result);
        $this->assertArrayNotHasKey('position', $result);
        $this->assertArrayNotHasKey('positionOhlc', $result);
        $this->assertArrayNotHasKey('meta', $result);
    }

    public function testSeriesPositionOhlcReturnsOnlyPositionOhlcLayer()
    {
        $result = $this->instance->buildChartResponse(192, 8, 'day', 'ranking', 'all', 'candlestick', false, null, null, 'positionOhlc');

        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('ohlcDate', $result);
        $this->assertArrayHasKey('positionOhlc', $result);
        $this->assertSame(count($result['ohlcDate']), count($result['positionOhlc']));
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
        $this->assertArrayHasKey('totalCount', $result);
        // フォールバック先もランキングなので time は持たない
        $this->assertArrayNotHasKey('time', $result);
    }
}

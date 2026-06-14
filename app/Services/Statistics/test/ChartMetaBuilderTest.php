<?php

/**
 * ChartMetaBuilderのテスト
 *
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Statistics/test/ChartMetaBuilderTest.php
 *
 * グラフ初回ロードの可用性メタを1部屋分だけ事前計算する処理が、
 * StatisticsChartArrayService と同じしきい値・同じ try/catch で組み立てられることを確認する。
 * DB依存（COUNT取得）は createStub で固定し、しきい値判定と例外時フォールバックの分岐だけを検証する。
 */

declare(strict_types=1);

use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Models\Repositories\RankingPosition\RankingPositionRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsPageRepositoryInterface;
use App\Services\Statistics\ChartMetaBuilder;
use App\Services\Storage\FileStorageInterface;
use PHPUnit\Framework\TestCase;

class ChartMetaBuilderTest extends TestCase
{
    /** 全種別の順位カウント（in/all × week/month/all） */
    private const POSITION_COUNTS = [
        'ranking_in' => ['week' => 0, 'month' => 3, 'all' => 5],
        'ranking_all' => ['week' => 1, 'month' => 0, 'all' => 0],
        'rising_in' => ['week' => 0, 'month' => 0, 'all' => 2],
        'rising_all' => ['week' => 0, 'month' => 0, 'all' => 0],
    ];

    private function pageRepo(?array $range): StatisticsPageRepositoryInterface
    {
        $stub = $this->createStub(StatisticsPageRepositoryInterface::class);
        $stub->method('getMemberDateRange')->willReturn($range);
        return $stub;
    }

    private function ohlcRepo(array $counts): StatisticsOhlcRepositoryInterface
    {
        $stub = $this->createStub(StatisticsOhlcRepositoryInterface::class);
        $stub->method('getOhlcCounts')->willReturn($counts);
        return $stub;
    }

    public function testReturnsNullWhenNoMemberStats(): void
    {
        $builder = new ChartMetaBuilder(
            $this->pageRepo(null),
            $this->createStub(StatisticsOhlcRepositoryInterface::class),
            $this->createStub(RankingPositionRepositoryInterface::class),
            $this->createStub(RankingPositionHourRepositoryInterface::class),
            $this->createStub(FileStorageInterface::class),
        );

        $this->assertNull($builder->build(123, 5), '統計レコードが無ければ null');
    }

    public function testBuildsFullMetaShapeAndThresholds(): void
    {
        // min..max = 10日 → len=10, weekWindow=8, monthWindow=10
        $pageRepo = $this->pageRepo(['min' => '2024-01-01', 'max' => '2024-01-10']);

        // week_count(8) >= weekWindow(8) → true / month_count(10)*2 >= monthWindow(10) → true / all_count>0 → all true
        $ohlcRepo = $this->ohlcRepo(['all_count' => 10, 'week_count' => 8, 'month_count' => 10]);

        $posRepo = $this->createStub(RankingPositionRepositoryInterface::class);
        $posRepo->method('getPositionCountsByPeriod')->willReturn(self::POSITION_COUNTS);

        $hourRepo = $this->createStub(RankingPositionHourRepositoryInterface::class);
        $hourRepo->method('getHourPositionCounts')->willReturn([
            'member' => 3, 'ranking_in' => 1, 'ranking_all' => 0, 'rising_in' => 0, 'rising_all' => 2,
        ]);

        $fileStorage = $this->createStub(FileStorageInterface::class);
        $fileStorage->method('getContents')->willReturn('2024-01-10 12:00:00');

        $builder = new ChartMetaBuilder($pageRepo, $ohlcRepo, $posRepo, $hourRepo, $fileStorage);
        $meta = $builder->build(123, 5);

        $this->assertNotNull($meta);
        $this->assertSame('2024-01-01', $meta['startDate']);
        $this->assertSame('2024-01-10', $meta['endDate']);
        $this->assertSame(10, $meta['dateCount']);

        // ohlc しきい値（ChartAvailabilityCalculator::dailyOhlc と同じ）
        $this->assertSame(['week' => true, 'month' => true, 'all' => true], $meta['ohlcAvailability']);

        // hour: member>0 → true、種別フラグは >0 判定
        $this->assertTrue($meta['hourAvailability']);
        $this->assertSame(
            ['ranking_in' => true, 'ranking_all' => false, 'rising_in' => false, 'rising_all' => true],
            $meta['positionAvailability']['hour'],
        );

        // week/month/all: dailyPosition と同じ >0 判定
        $this->assertSame(
            ['ranking_in' => false, 'ranking_all' => true, 'rising_in' => false, 'rising_all' => false],
            $meta['positionAvailability']['week'],
        );
        $this->assertSame(
            ['ranking_in' => true, 'ranking_all' => false, 'rising_in' => false, 'rising_all' => false],
            $meta['positionAvailability']['month'],
        );
        $this->assertSame(
            ['ranking_in' => true, 'ranking_all' => false, 'rising_in' => true, 'rising_all' => false],
            $meta['positionAvailability']['all'],
        );

        // meta ブロックのキー集合が OpenChatChartApiService::buildChartResponse と一致する
        $this->assertSame(
            ['startDate', 'endDate', 'dateCount', 'hourAvailability', 'positionAvailability', 'ohlcAvailability'],
            array_keys($meta),
        );
        $this->assertSame(
            ['hour', 'week', 'month', 'all'],
            array_keys($meta['positionAvailability']),
        );
    }

    public function testOhlcAllZeroMakesOhlcAllFalse(): void
    {
        $pageRepo = $this->pageRepo(['min' => '2024-01-01', 'max' => '2024-01-10']);
        $ohlcRepo = $this->ohlcRepo(['all_count' => 0, 'week_count' => 0, 'month_count' => 0]);

        $posRepo = $this->createStub(RankingPositionRepositoryInterface::class);
        $posRepo->method('getPositionCountsByPeriod')->willReturn(self::POSITION_COUNTS);

        $hourRepo = $this->createStub(RankingPositionHourRepositoryInterface::class);
        $hourRepo->method('getHourPositionCounts')->willReturn([
            'member' => 0, 'ranking_in' => 0, 'ranking_all' => 0, 'rising_in' => 0, 'rising_all' => 0,
        ]);

        $fileStorage = $this->createStub(FileStorageInterface::class);
        $fileStorage->method('getContents')->willReturn('2024-01-10 12:00:00');

        $builder = new ChartMetaBuilder($pageRepo, $ohlcRepo, $posRepo, $hourRepo, $fileStorage);
        $meta = $builder->build(123, 5);

        $this->assertNotNull($meta);
        $this->assertSame(['week' => false, 'month' => false, 'all' => false], $meta['ohlcAvailability']);
        $this->assertFalse($meta['hourAvailability'], 'member=0 → hour 無し');
    }

    public function testPositionPdoExceptionMakesWeekMonthAllFalseButHourStillComputed(): void
    {
        $pageRepo = $this->pageRepo(['min' => '2024-01-01', 'max' => '2024-01-10']);
        $ohlcRepo = $this->ohlcRepo(['all_count' => 10, 'week_count' => 8, 'month_count' => 10]);

        // 日次順位DB未作成 → PDOException
        $posRepo = $this->createStub(RankingPositionRepositoryInterface::class);
        $posRepo->method('getPositionCountsByPeriod')->willThrowException(new \PDOException('no such table'));

        // 毎時は正常に取得できる（builderは日次例外でも hour を独立して計算する）
        $hourRepo = $this->createStub(RankingPositionHourRepositoryInterface::class);
        $hourRepo->method('getHourPositionCounts')->willReturn([
            'member' => 5, 'ranking_in' => 0, 'ranking_all' => 2, 'rising_in' => 0, 'rising_all' => 0,
        ]);

        $fileStorage = $this->createStub(FileStorageInterface::class);
        $fileStorage->method('getContents')->willReturn('2024-01-10 12:00:00');

        $builder = new ChartMetaBuilder($pageRepo, $ohlcRepo, $posRepo, $hourRepo, $fileStorage);
        $meta = $builder->build(123, 5);

        $this->assertNotNull($meta);
        // ohlc は日次順位とは別DBなので影響を受けない
        $this->assertSame(['week' => true, 'month' => true, 'all' => true], $meta['ohlcAvailability']);

        $none = ['ranking_in' => false, 'ranking_all' => false, 'rising_in' => false, 'rising_all' => false];
        foreach (['week', 'month', 'all'] as $period) {
            $this->assertSame($none, $meta['positionAvailability'][$period], "{$period} は順位DB例外で全false");
        }

        // hour は独立して計算され続ける
        $this->assertTrue($meta['hourAvailability']);
        $this->assertSame(
            ['ranking_in' => false, 'ranking_all' => true, 'rising_in' => false, 'rising_all' => false],
            $meta['positionAvailability']['hour'],
        );
    }

    public function testHourExceptionMakesHourFalseButDailyStillComputed(): void
    {
        $pageRepo = $this->pageRepo(['min' => '2024-01-01', 'max' => '2024-01-10']);
        $ohlcRepo = $this->ohlcRepo(['all_count' => 10, 'week_count' => 8, 'month_count' => 10]);

        $posRepo = $this->createStub(RankingPositionRepositoryInterface::class);
        $posRepo->method('getPositionCountsByPeriod')->willReturn(self::POSITION_COUNTS);

        $hourRepo = $this->createStub(RankingPositionHourRepositoryInterface::class);

        // 毎時クロール時刻ファイルが無い等 → \Throwable
        $fileStorage = $this->createStub(FileStorageInterface::class);
        $fileStorage->method('getContents')->willThrowException(new \RuntimeException('no file'));

        $builder = new ChartMetaBuilder($pageRepo, $ohlcRepo, $posRepo, $hourRepo, $fileStorage);
        $meta = $builder->build(123, 5);

        $this->assertNotNull($meta);
        // 日次（ohlc・週/月/全順位）は hour 例外の影響を受けない
        $this->assertSame(['week' => true, 'month' => true, 'all' => true], $meta['ohlcAvailability']);
        $this->assertSame(
            ['ranking_in' => false, 'ranking_all' => true, 'rising_in' => false, 'rising_all' => false],
            $meta['positionAvailability']['week'],
        );

        // hour は全 false
        $this->assertFalse($meta['hourAvailability']);
        $this->assertSame(
            ['ranking_in' => false, 'ranking_all' => false, 'rising_in' => false, 'rising_all' => false],
            $meta['positionAvailability']['hour'],
        );
    }

    public function testNoCategoryUsesMinusOneInCategory(): void
    {
        // category null（その他/未掲載）→ in 判定は -1（不一致値）で呼ばれる
        $pageRepo = $this->pageRepo(['min' => '2024-01-01', 'max' => '2024-01-08']);
        $ohlcRepo = $this->ohlcRepo(['all_count' => 8, 'week_count' => 8, 'month_count' => 8]);

        $posRepo = $this->createMock(RankingPositionRepositoryInterface::class);
        $posRepo->expects($this->once())
            ->method('getPositionCountsByPeriod')
            ->with(123, -1, $this->isString(), $this->isString())
            ->willReturn(self::POSITION_COUNTS);

        $hourRepo = $this->createMock(RankingPositionHourRepositoryInterface::class);
        $hourRepo->expects($this->once())
            ->method('getHourPositionCounts')
            ->with(123, -1, 24, $this->isInstanceOf(\DateTime::class))
            ->willReturn(['member' => 0, 'ranking_in' => 0, 'ranking_all' => 0, 'rising_in' => 0, 'rising_all' => 0]);

        $fileStorage = $this->createStub(FileStorageInterface::class);
        $fileStorage->method('getContents')->willReturn('2024-01-08 12:00:00');

        $builder = new ChartMetaBuilder($pageRepo, $ohlcRepo, $posRepo, $hourRepo, $fileStorage);
        $meta = $builder->build(123, null);

        $this->assertNotNull($meta);
    }
}

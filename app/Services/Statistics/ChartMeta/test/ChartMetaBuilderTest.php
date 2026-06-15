<?php

/**
 * ChartMetaBuilderのテスト
 *
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Statistics/ChartMeta/test/ChartMetaBuilderTest.php
 *
 * グラフ初回ロードの可用性メタを1部屋分だけ組み立てる処理（埋め込みもライブも同じ Builder）が、
 * しきい値・例外時フォールバックの分岐どおりに動くことを確認する。
 * DB依存（COUNT取得）は createStub で固定し、しきい値判定と例外時フォールバックの分岐だけを検証する。
 *
 * 最新24時間タブの集計(hourEntry)は cron 経路では呼び出し側が一括取得して build に渡す。
 * 本テストでは hourEntry を直接渡し、in/all 判定と「出現なし(空集計)」時の全 false を検証する。
 * ライブ経路（hourEntry 省略＝per-room 取得）は getHourPositionCounts スタブで別途検証する。
 */

declare(strict_types=1);

use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Models\Repositories\RankingPosition\RankingPositionRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsPageRepositoryInterface;
use App\Services\Statistics\ChartMeta\ChartMetaBuilder;
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

    private function posRepo(): RankingPositionRepositoryInterface
    {
        $stub = $this->createStub(RankingPositionRepositoryInterface::class);
        $stub->method('getPositionCountsByPeriod')->willReturn(self::POSITION_COUNTS);
        return $stub;
    }

    /**
     * cron 経路（hourEntry を渡す）のテスト用に毎時リポジトリ／FileStorage を組み立てる。
     * これらは hourEntry を渡す限り build() からは触られない（呼ばれたら fail させて検出する）。
     */
    private function unusedHourRepo(): RankingPositionHourRepositoryInterface
    {
        $mock = $this->createMock(RankingPositionHourRepositoryInterface::class);
        $mock->expects($this->never())->method('getHourPositionCounts');
        return $mock;
    }

    private function unusedFileStorage(): FileStorageInterface
    {
        $mock = $this->createMock(FileStorageInterface::class);
        $mock->expects($this->never())->method('getContents');
        return $mock;
    }

    /** ライブ経路用: per-room の getHourPositionCounts が返す 5 件のカウント */
    private function liveHourRepo(array $counts): RankingPositionHourRepositoryInterface
    {
        $stub = $this->createStub(RankingPositionHourRepositoryInterface::class);
        $stub->method('getHourPositionCounts')->willReturn($counts);
        return $stub;
    }

    private function fileStorage(): FileStorageInterface
    {
        $stub = $this->createStub(FileStorageInterface::class);
        $stub->method('getContents')->willReturn('2024-01-10 12:00:00');
        return $stub;
    }

    /** cron 経路テスト（hourEntry を渡す）用の Builder。毎時リポジトリ／FileStorage は不使用 */
    private function builder(
        StatisticsPageRepositoryInterface $pageRepo,
        StatisticsOhlcRepositoryInterface $ohlcRepo,
        RankingPositionRepositoryInterface $posRepo,
    ): ChartMetaBuilder {
        return new ChartMetaBuilder($pageRepo, $ohlcRepo, $posRepo, $this->unusedHourRepo(), $this->unusedFileStorage());
    }

    public function testReturnsNullWhenNoMemberStats(): void
    {
        $builder = $this->builder(
            $this->pageRepo(null),
            $this->createStub(StatisticsOhlcRepositoryInterface::class),
            $this->createStub(RankingPositionRepositoryInterface::class),
        );

        $this->assertNull($builder->build(123, 5, null), '統計レコードが無ければ null');
    }

    public function testBuildsFullMetaShapeAndThresholds(): void
    {
        // min..max = 10日 → len=10, weekWindow=8, monthWindow=10
        $pageRepo = $this->pageRepo(['min' => '2024-01-01', 'max' => '2024-01-10']);

        // week_count(8) >= weekWindow(8) → true / month_count(10)*2 >= monthWindow(10) → true / all_count>0 → all true
        $ohlcRepo = $this->ohlcRepo(['all_count' => 10, 'week_count' => 8, 'month_count' => 10]);

        // hour: member=true、ranking はカテゴリ5に出現（in true）、rising は全体0に出現（all true）
        $hourEntry = ['member' => true, 'ranking' => [5], 'rising' => [0]];

        $builder = $this->builder($pageRepo, $ohlcRepo, $this->posRepo());
        $meta = $builder->build(123, 5, $hourEntry);

        $this->assertNotNull($meta);
        $this->assertSame('2024-01-01', $meta['startDate']);
        $this->assertSame('2024-01-10', $meta['endDate']);
        $this->assertSame(10, $meta['dateCount']);

        // ohlc しきい値（ChartAvailabilityCalculator::dailyOhlc と同じ）
        $this->assertSame(['week' => true, 'month' => true, 'all' => true], $meta['ohlcAvailability']);

        // hour: member=true → hourAvailability true、種別フラグは出現カテゴリで判定
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

        // meta ブロックのキー集合（risingStatus はブログ導線の状態駆動用にキャッシュへ相乗りさせた派生データ）
        $this->assertSame(
            ['startDate', 'endDate', 'dateCount', 'hourAvailability', 'positionAvailability', 'ohlcAvailability', 'risingStatus'],
            array_keys($meta),
        );
        // risingStatus: 週窓の掲載状態（このfixtureでは week.ranking_all=true / rising無し / top5無し）
        $this->assertSame(
            ['on_ranking_week' => true, 'on_rising_week' => false, 'top5_all_week' => false],
            $meta['risingStatus'],
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

        // hour: member=false（出現はあったが member レコード無し）→ hourAvailability false
        $hourEntry = ['member' => false, 'ranking' => [], 'rising' => []];

        $builder = $this->builder($pageRepo, $ohlcRepo, $this->posRepo());
        $meta = $builder->build(123, 5, $hourEntry);

        $this->assertNotNull($meta);
        $this->assertSame(['week' => false, 'month' => false, 'all' => false], $meta['ohlcAvailability']);
        $this->assertFalse($meta['hourAvailability'], 'member=false → hour 無し');
    }

    public function testPositionPdoExceptionMakesWeekMonthAllFalseButHourStillComputed(): void
    {
        $pageRepo = $this->pageRepo(['min' => '2024-01-01', 'max' => '2024-01-10']);
        $ohlcRepo = $this->ohlcRepo(['all_count' => 10, 'week_count' => 8, 'month_count' => 10]);

        // 日次順位DB未作成 → PDOException
        $posRepo = $this->createStub(RankingPositionRepositoryInterface::class);
        $posRepo->method('getPositionCountsByPeriod')->willThrowException(new \PDOException('no such table'));

        // 毎時は呼び出し側が用意済み（builderは日次例外でも hour を独立して計算する）
        $hourEntry = ['member' => true, 'ranking' => [0], 'rising' => []];

        $builder = $this->builder($pageRepo, $ohlcRepo, $posRepo);
        $meta = $builder->build(123, 5, $hourEntry);

        $this->assertNotNull($meta);
        // ohlc は日次順位とは別DBなので影響を受けない
        $this->assertSame(['week' => true, 'month' => true, 'all' => true], $meta['ohlcAvailability']);

        $none = ['ranking_in' => false, 'ranking_all' => false, 'rising_in' => false, 'rising_all' => false];
        foreach (['week', 'month', 'all'] as $period) {
            $this->assertSame($none, $meta['positionAvailability'][$period], "{$period} は順位DB例外で全false");
        }

        // hour は独立して計算され続ける（ranking 全体0に出現 → ranking_all true）
        $this->assertTrue($meta['hourAvailability']);
        $this->assertSame(
            ['ranking_in' => false, 'ranking_all' => true, 'rising_in' => false, 'rising_all' => false],
            $meta['positionAvailability']['hour'],
        );
    }

    public function testHourEntryNoneMakesHourFalseButDailyStillComputed(): void
    {
        $pageRepo = $this->pageRepo(['min' => '2024-01-01', 'max' => '2024-01-10']);
        $ohlcRepo = $this->ohlcRepo(['all_count' => 10, 'week_count' => 8, 'month_count' => 10]);

        // cron 経路で「直近24hに出現なし」の部屋は空集計(hourEntryNone)を渡す → hour 全 false。
        // （per-room の getHourPositionCounts は撃たない＝unusedHourRepo で検出）
        $builder = $this->builder($pageRepo, $ohlcRepo, $this->posRepo());
        $meta = $builder->build(123, 5, ChartMetaBuilder::hourEntryNone());

        $this->assertNotNull($meta);
        // 日次（ohlc・週/月/全順位）は hour の有無に影響されない
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

    public function testLiveHourEntryOmittedFetchesPerRoomCounts(): void
    {
        // ライブ経路（hourEntry を省略＝null）は per-room の getHourPositionCounts を撃ち、
        // その 5 件のカウントを >0 判定で hour に反映する（旧 StatisticsChartArrayService と同じ）。
        $pageRepo = $this->pageRepo(['min' => '2024-01-01', 'max' => '2024-01-10']);
        $ohlcRepo = $this->ohlcRepo(['all_count' => 10, 'week_count' => 8, 'month_count' => 10]);

        // member>0 → hourAvailability true / ranking_in>0,rising_all>0 のみ true
        $hourRepo = $this->liveHourRepo([
            'member' => 4,
            'ranking_in' => 2,
            'ranking_all' => 0,
            'rising_in' => 0,
            'rising_all' => 3,
        ]);

        $builder = new ChartMetaBuilder($pageRepo, $ohlcRepo, $this->posRepo(), $hourRepo, $this->fileStorage());
        $meta = $builder->build(123, 5); // hourEntry 省略＝ライブ

        $this->assertNotNull($meta);
        $this->assertTrue($meta['hourAvailability']);
        $this->assertSame(
            ['ranking_in' => true, 'ranking_all' => false, 'rising_in' => false, 'rising_all' => true],
            $meta['positionAvailability']['hour'],
        );
    }

    public function testLiveHourThrowsMakesHourFalseButDailyStillComputed(): void
    {
        // ライブ経路で毎時クロール未実行・DB未作成（getContents/getHourPositionCounts が例外）の場合、
        // hour は全 false にフォールバックし、日次（ohlc・週/月/全）は計算され続ける。
        $pageRepo = $this->pageRepo(['min' => '2024-01-01', 'max' => '2024-01-10']);
        $ohlcRepo = $this->ohlcRepo(['all_count' => 10, 'week_count' => 8, 'month_count' => 10]);

        $hourRepo = $this->createStub(RankingPositionHourRepositoryInterface::class);
        $hourRepo->method('getHourPositionCounts')->willThrowException(new \PDOException('no such table'));

        $builder = new ChartMetaBuilder($pageRepo, $ohlcRepo, $this->posRepo(), $hourRepo, $this->fileStorage());
        $meta = $builder->build(123, 5); // ライブ

        $this->assertNotNull($meta);
        $this->assertSame(['week' => true, 'month' => true, 'all' => true], $meta['ohlcAvailability']);
        $this->assertSame(
            ['ranking_in' => false, 'ranking_all' => true, 'rising_in' => false, 'rising_all' => false],
            $meta['positionAvailability']['week'],
        );
        $this->assertFalse($meta['hourAvailability']);
        $this->assertSame(
            ['ranking_in' => false, 'ranking_all' => false, 'rising_in' => false, 'rising_all' => false],
            $meta['positionAvailability']['hour'],
        );
    }

    public function testNoCategoryHourInIsAlwaysFalse(): void
    {
        // category null（その他/未掲載）→ カテゴリ内(in)には出現しえないので in は常に false。
        // hourEntry にカテゴリ値が入っていても（=全体掲載のみ）、in は false になること。
        $pageRepo = $this->pageRepo(['min' => '2024-01-01', 'max' => '2024-01-08']);
        $ohlcRepo = $this->ohlcRepo(['all_count' => 8, 'week_count' => 8, 'month_count' => 8]);

        // category=null の部屋は in 判定に使うカテゴリが無い。ranking/rising が全体0に出現していれば all のみ true。
        $hourEntry = ['member' => true, 'ranking' => [0], 'rising' => [0]];

        // 日次順位もカテゴリ未設定は in=false を返す前提（-1 で呼ばれる）
        $posRepo = $this->createMock(RankingPositionRepositoryInterface::class);
        $posRepo->expects($this->once())
            ->method('getPositionCountsByPeriod')
            ->with(123, -1, $this->isString(), $this->isString())
            ->willReturn(self::POSITION_COUNTS);

        $builder = $this->builder($pageRepo, $ohlcRepo, $posRepo);
        $meta = $builder->build(123, null, $hourEntry);

        $this->assertNotNull($meta);
        $this->assertTrue($meta['hourAvailability']);
        $this->assertSame(
            ['ranking_in' => false, 'ranking_all' => true, 'rising_in' => false, 'rising_all' => true],
            $meta['positionAvailability']['hour'],
            'category null は in 常に false、all は全体0出現で true',
        );
    }

    public function testHourInBoundaryCategoryZeroIsNotIn(): void
    {
        // しきい値境界: category=0 は「全体(all)」であって「カテゴリ内(in)」ではない。
        // category>0 の部屋でも ranking に 0 しか無ければ ranking_in=false / ranking_all=true。
        $pageRepo = $this->pageRepo(['min' => '2024-01-01', 'max' => '2024-01-08']);
        $ohlcRepo = $this->ohlcRepo(['all_count' => 8, 'week_count' => 8, 'month_count' => 8]);

        $hourEntry = ['member' => true, 'ranking' => [0], 'rising' => [5]];

        $builder = $this->builder($pageRepo, $ohlcRepo, $this->posRepo());
        $meta = $builder->build(123, 5, $hourEntry);

        $this->assertNotNull($meta);
        $this->assertSame(
            // ranking: [0] → all true / in(5) false。rising: [5] → in(5) true / all(0) false
            ['ranking_in' => false, 'ranking_all' => true, 'rising_in' => true, 'rising_all' => false],
            $meta['positionAvailability']['hour'],
        );
    }
}

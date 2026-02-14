<?php

/**
 * RankingPositionDailyUpdaterのOHLC永続化ロジックの結合テスト
 *
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/RankingPosition/test/RankingPositionDailyUpdaterOhlcTest.php
 *
 * テスト内容:
 * - 参照側（RankingPositionHourRepository）をモックして既知のデータを返す
 * - OHLC永続化側（StatisticsOhlcRepository, RankingPositionOhlcRepository）を
 *   FileStorageInterfaceモック経由で実際のSQLiteに書き込み、正しく保存されたか検証
 * - RankingPositionDailyUpdaterのupdateYesterdayDailyDb()がOHLCデータを
 *   正しく変換・フィルタリング・保存することを検証
 */

declare(strict_types=1);

use App\Models\Repositories\OpenChatRepositoryInterface;
use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Models\Repositories\RankingPosition\RankingPositionOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Models\SQLite\Repositories\RankingPosition\SqliteRankingPositionOhlcRepository;
use App\Models\SQLite\Repositories\Statistics\SqliteStatisticsOhlcRepository;
use App\Models\SQLite\SQLiteRankingPositionOhlc;
use App\Models\SQLite\SQLiteStatisticsOhlc;
use App\Services\Cron\Enum\SyncOpenChatStateType;
use App\Services\OpenChat\Enum\RankingType;
use App\Services\RankingPosition\Persistence\RankingPositionDailyPersistence;
use App\Services\RankingPosition\RankingPositionDailyUpdater;
use App\Config\AppConfig;
use App\Services\Storage\FileStorageInterface;
use Shadow\Kernel\Dispatcher\ConstructorInjection;
use PHPUnit\Framework\TestCase;

class RankingPositionDailyUpdaterOhlcTest extends TestCase
{
    private string $tempDir;
    private mixed $originalStorage;
    private bool $originalVerboseCronLog;

    protected function setUp(): void
    {
        $this->originalVerboseCronLog = AppConfig::$verboseCronLog;
        AppConfig::$verboseCronLog = false;

        $this->tempDir = sys_get_temp_dir() . '/test_daily_updater_ohlc_' . uniqid();
        mkdir($this->tempDir . '/SQLite/statistics_ohlc', 0777, true);
        mkdir($this->tempDir . '/SQLite/ranking_position_ohlc', 0777, true);

        $this->originalStorage = ConstructorInjection::$container[FileStorageInterface::class] ?? null;

        $tempDir = $this->tempDir;
        $mockStorage = $this->createMock(FileStorageInterface::class);
        $mockStorage->method('getStorageFilePath')
            ->willReturnCallback(fn(string $key) => match ($key) {
                'sqliteStatisticsOhlcDb' => $tempDir . '/SQLite/statistics_ohlc/statistics_ohlc.db',
                'sqliteRankingPositionOhlcDb' => $tempDir . '/SQLite/ranking_position_ohlc/ranking_position_ohlc.db',
                default => '',
            });

        ConstructorInjection::$container[FileStorageInterface::class] = [
            'concrete' => $mockStorage,
            'singleton' => ['flag' => true],
        ];

        SQLiteStatisticsOhlc::$pdo = null;
        SQLiteRankingPositionOhlc::$pdo = null;
    }

    protected function tearDown(): void
    {
        AppConfig::$verboseCronLog = $this->originalVerboseCronLog;

        SQLiteStatisticsOhlc::$pdo = null;
        SQLiteRankingPositionOhlc::$pdo = null;

        // 一時ファイルを削除
        foreach (['statistics_ohlc', 'ranking_position_ohlc'] as $subdir) {
            $dir = $this->tempDir . '/SQLite/' . $subdir;
            foreach (glob($dir . '/*') as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
        @rmdir($this->tempDir . '/SQLite');
        @rmdir($this->tempDir);

        if ($this->originalStorage !== null) {
            ConstructorInjection::$container[FileStorageInterface::class] = $this->originalStorage;
        } else {
            unset(ConstructorInjection::$container[FileStorageInterface::class]);
        }
    }

    /**
     * @test updateYesterdayDailyDb が OHLC データを正しく永続化すること
     * - 参照側（HourRepository, OpenChatRepository等）をモック、永続化側は実SQLite
     * - メンバー数OHLCが statistics_ohlc に保存されること
     * - ランキング順位OHLC（ranking/rising）が ranking_position_ohlc に保存されること
     * - DBに存在しない open_chat_id（9999）はフィルタリングされて保存されないこと
     * - rising の圏外あり low_position=NULL が正しく保存されること
     * - 実行日付が syncState に記録されること
     */
    public function testUpdateYesterdayDailyDbPersistsOhlcData(): void
    {
        $testDate = '2025-01-15';

        // --- 参照側モック ---

        // RankingPositionHourRepository: getDailyMemberStats, getDailyMemberOhlc, getDailyPositionOhlc
        $hourRepo = $this->createMock(RankingPositionHourRepositoryInterface::class);

        $hourRepo->method('getDailyMemberStats')->willReturn([
            ['open_chat_id' => 1001, 'member' => 110, 'date' => $testDate],
            ['open_chat_id' => 1002, 'member' => 200, 'date' => $testDate],
            ['open_chat_id' => 9999, 'member' => 50, 'date' => $testDate], // DBに存在しないID
        ]);

        $hourRepo->method('getDailyMemberOhlc')->willReturn([
            [
                'open_chat_id' => 1001,
                'open_member' => 100,
                'high_member' => 120,
                'low_member' => 95,
                'close_member' => 110,
                'date' => $testDate,
            ],
            [
                'open_chat_id' => 1002,
                'open_member' => 200,
                'high_member' => 200,
                'low_member' => 200,
                'close_member' => 200,
                'date' => $testDate,
            ],
            [
                'open_chat_id' => 9999, // DBに存在しないID（フィルタリングされる）
                'open_member' => 50,
                'high_member' => 60,
                'low_member' => 40,
                'close_member' => 55,
                'date' => $testDate,
            ],
        ]);

        $hourRepo->method('getDailyPositionOhlc')->willReturnCallback(
            function (RankingType $type, \DateTime $date) use ($testDate) {
                if ($type === RankingType::Ranking) {
                    return [
                        [
                            'open_chat_id' => 1001,
                            'category' => 0,
                            'open_position' => 5,
                            'high_position' => 3,
                            'low_position' => 8,
                            'close_position' => 4,
                            'date' => $testDate,
                        ],
                    ];
                }
                // Rising
                return [
                    [
                        'open_chat_id' => 1001,
                        'category' => 0,
                        'open_position' => 10,
                        'high_position' => 5,
                        'low_position' => null,
                        'close_position' => 7,
                        'date' => $testDate,
                    ],
                ];
            }
        );

        // OpenChatRepository: DBに存在するIDのリスト（9999は含まない）
        $openChatRepo = $this->createMock(OpenChatRepositoryInterface::class);
        $openChatRepo->method('getOpenChatIdAll')->willReturn([1001, 1002]);

        // SyncOpenChatStateRepository: 実行済みチェック→未実行
        $syncStateRepo = $this->createMock(SyncOpenChatStateRepositoryInterface::class);
        $syncStateRepo->method('getString')->willReturn('');
        $syncStateRepo->expects($this->once())
            ->method('setString')
            ->with(SyncOpenChatStateType::persistMemberStatsLastDate, $this->anything());

        // StatisticsRepository: insertMemberは呼ばれるが中身は検証しない
        $statsRepo = $this->createMock(StatisticsRepositoryInterface::class);
        $statsRepo->method('insertMember')->willReturn(0);

        // RankingPositionDailyPersistence: persistHourToDailyは空の処理
        $dailyPersistence = $this->createMock(RankingPositionDailyPersistence::class);

        // --- 永続化側は実SQLiteリポジトリを使用 ---
        $statsOhlcRepo = app(SqliteStatisticsOhlcRepository::class);
        $rankingOhlcRepo = app(SqliteRankingPositionOhlcRepository::class);

        // --- RankingPositionDailyUpdaterをコンストラクタ引数付きで生成 ---
        // staticなgetCronModifiedStatsMemberDateをオーバーライドできないので
        // テスト対象のメソッドを直接テスト
        $updater = new RankingPositionDailyUpdater(
            $dailyPersistence,
            $statsRepo,
            $statsOhlcRepo,
            $rankingOhlcRepo,
            $hourRepo,
            $openChatRepo,
            $syncStateRepo,
        );

        // --- 実行 ---
        $updater->updateYesterdayDailyDb();

        // --- 検証: statistics_ohlc ---
        SQLiteStatisticsOhlc::$pdo = null;
        $memberOhlc = $statsOhlcRepo->getOhlcDateAsc(1001);
        $this->assertCount(1, $memberOhlc, 'ID 1001のOHLCが保存されていること');
        $this->assertEquals(100, $memberOhlc[0]['open_member']);
        $this->assertEquals(120, $memberOhlc[0]['high_member']);
        $this->assertEquals(95, $memberOhlc[0]['low_member']);
        $this->assertEquals(110, $memberOhlc[0]['close_member']);

        // ID 1002も保存されていること
        $memberOhlc2 = $statsOhlcRepo->getOhlcDateAsc(1002);
        $this->assertCount(1, $memberOhlc2);

        // ID 9999はフィルタリングされて保存されないこと
        $memberOhlc9999 = $statsOhlcRepo->getOhlcDateAsc(9999);
        $this->assertCount(0, $memberOhlc9999, 'DBに存在しないIDはフィルタリングされる');

        // --- 検証: ranking_position_ohlc ---
        SQLiteRankingPositionOhlc::$pdo = null;
        $rankingOhlc = $rankingOhlcRepo->getOhlcDateAsc(1001, 0, RankingType::Ranking);
        $this->assertCount(1, $rankingOhlc, 'Ranking OHLCが保存されていること');
        $this->assertEquals(5, $rankingOhlc[0]['open_position']);
        $this->assertEquals(3, $rankingOhlc[0]['high_position']);
        $this->assertEquals(8, $rankingOhlc[0]['low_position']);
        $this->assertEquals(4, $rankingOhlc[0]['close_position']);

        $risingOhlc = $rankingOhlcRepo->getOhlcDateAsc(1001, 0, RankingType::Rising);
        $this->assertCount(1, $risingOhlc, 'Rising OHLCが保存されていること');
        $this->assertNull($risingOhlc[0]['low_position'], '圏外ありの場合low_positionはNULL');
    }

    /**
     * @test 同日に既に実行済みの場合はスキップされること
     * - syncState に当日日付が記録済み → insertOhlc が呼ばれないこと
     */
    public function testUpdateYesterdayDailyDbSkipsWhenAlreadyExecuted(): void
    {
        $dailyPersistence = $this->createMock(RankingPositionDailyPersistence::class);
        $statsRepo = $this->createMock(StatisticsRepositoryInterface::class);
        $statsOhlcRepo = $this->createMock(StatisticsOhlcRepositoryInterface::class);
        $rankingOhlcRepo = $this->createMock(RankingPositionOhlcRepositoryInterface::class);
        $hourRepo = $this->createMock(RankingPositionHourRepositoryInterface::class);
        $openChatRepo = $this->createMock(OpenChatRepositoryInterface::class);

        // getString が現在の日付を返す → 実行済み → スキップ
        $syncStateRepo = $this->createMock(SyncOpenChatStateRepositoryInterface::class);
        $syncStateRepo->method('getString')->willReturnCallback(
            fn() => \App\Services\OpenChat\Utility\OpenChatServicesUtility::getCronModifiedStatsMemberDate()
        );

        // insertOhlcが呼ばれないことを検証
        $statsOhlcRepo->expects($this->never())->method('insertOhlc');
        $rankingOhlcRepo->expects($this->never())->method('insertOhlc');

        $updater = new RankingPositionDailyUpdater(
            $dailyPersistence,
            $statsRepo,
            $statsOhlcRepo,
            $rankingOhlcRepo,
            $hourRepo,
            $openChatRepo,
            $syncStateRepo,
        );

        $updater->updateYesterdayDailyDb();
    }
}

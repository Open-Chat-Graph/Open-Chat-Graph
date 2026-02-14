<?php

/**
 * SqliteRankingPositionOhlcRepositoryのテスト
 *
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Models/SQLite/Repositories/RankingPosition/test/SqliteRankingPositionOhlcRepositoryTest.php
 *
 * テスト対象メソッド:
 * - insertOhlc() - ランキング順位OHLCデータの挿入
 * - getOhlcDateAsc() - ランキング順位OHLCデータの日付昇順取得
 */

declare(strict_types=1);

use App\Models\SQLite\Repositories\RankingPosition\SqliteRankingPositionOhlcRepository;
use App\Models\SQLite\SQLiteRankingPositionOhlc;
use App\Services\OpenChat\Enum\RankingType;
use App\Services\Storage\FileStorageInterface;
use Shadow\Kernel\Dispatcher\ConstructorInjection;
use PHPUnit\Framework\TestCase;

class SqliteRankingPositionOhlcRepositoryTest extends TestCase
{
    private SqliteRankingPositionOhlcRepository $repository;
    private string $tempDir;
    private mixed $originalStorage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_ranking_pos_ohlc_' . uniqid();
        mkdir($this->tempDir . '/SQLite/ranking_position_ohlc', 0777, true);

        $this->originalStorage = ConstructorInjection::$container[FileStorageInterface::class] ?? null;

        $dbPath = $this->tempDir . '/SQLite/ranking_position_ohlc/ranking_position_ohlc.db';
        $mockStorage = $this->createMock(FileStorageInterface::class);
        $mockStorage->method('getStorageFilePath')
            ->willReturnCallback(fn(string $key) => match ($key) {
                'sqliteRankingPositionOhlcDb' => $dbPath,
                default => '',
            });

        ConstructorInjection::$container[FileStorageInterface::class] = [
            'concrete' => $mockStorage,
            'singleton' => ['flag' => true],
        ];

        SQLiteRankingPositionOhlc::$pdo = null;

        $this->repository = app(SqliteRankingPositionOhlcRepository::class);
    }

    protected function tearDown(): void
    {
        SQLiteRankingPositionOhlc::$pdo = null;

        $dbFile = $this->tempDir . '/SQLite/ranking_position_ohlc/ranking_position_ohlc.db';
        foreach (glob($dbFile . '*') as $f) {
            @unlink($f);
        }
        @rmdir($this->tempDir . '/SQLite/ranking_position_ohlc');
        @rmdir($this->tempDir . '/SQLite');
        @rmdir($this->tempDir);

        if ($this->originalStorage !== null) {
            ConstructorInjection::$container[FileStorageInterface::class] = $this->originalStorage;
        } else {
            unset(ConstructorInjection::$container[FileStorageInterface::class]);
        }
    }

    /**
     * @test insertOhlc → getOhlcDateAsc の基本フロー
     * - 1件挿入して1件返ること
     * - date, open/high/low/close_position が一致すること
     */
    public function testInsertOhlcAndGetOhlcDateAsc(): void
    {
        $data = [
            [
                'open_chat_id' => 1001,
                'category' => 0,
                'type' => 'ranking',
                'open_position' => 5,
                'high_position' => 3,
                'low_position' => 8,
                'close_position' => 4,
                'date' => '2025-01-15',
            ],
        ];

        $count = $this->repository->insertOhlc($data);
        $this->assertSame(1, $count);

        SQLiteRankingPositionOhlc::$pdo = null;

        $result = $this->repository->getOhlcDateAsc(1001, 0, RankingType::Ranking);

        $this->assertCount(1, $result);
        $this->assertSame('2025-01-15', $result[0]['date']);
        $this->assertEquals(5, $result[0]['open_position']);
        $this->assertEquals(3, $result[0]['high_position']);
        $this->assertEquals(8, $result[0]['low_position']);
        $this->assertEquals(4, $result[0]['close_position']);
    }

    /**
     * @test low_position=NULL（圏外あり）のデータが正しく挿入・取得できること
     * - 挿入時に null → SQLの NULL として保存
     * - 取得時に PHP の null として返ること
     */
    public function testInsertOhlcWithNullLowPosition(): void
    {
        $data = [
            [
                'open_chat_id' => 2001,
                'category' => 1,
                'type' => 'rising',
                'open_position' => 10,
                'high_position' => 5,
                'low_position' => null,
                'close_position' => 7,
                'date' => '2025-01-15',
            ],
        ];

        $count = $this->repository->insertOhlc($data);
        $this->assertSame(1, $count);

        SQLiteRankingPositionOhlc::$pdo = null;

        $result = $this->repository->getOhlcDateAsc(2001, 1, RankingType::Rising);

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['low_position'], 'low_positionがNULLであること');
        $this->assertEquals(10, $result[0]['open_position']);
    }

    /**
     * @test getOhlcDateAsc が category と type で正しくフィルタリングすること
     * - 同一 open_chat_id でも category/type 違いは別レコード
     */
    public function testGetOhlcDateAscFiltersByCategoryAndType(): void
    {
        $data = [
            [
                'open_chat_id' => 3001,
                'category' => 0,
                'type' => 'ranking',
                'open_position' => 1,
                'high_position' => 1,
                'low_position' => 3,
                'close_position' => 2,
                'date' => '2025-01-15',
            ],
            [
                'open_chat_id' => 3001,
                'category' => 0,
                'type' => 'rising',
                'open_position' => 10,
                'high_position' => 5,
                'low_position' => 15,
                'close_position' => 8,
                'date' => '2025-01-15',
            ],
            [
                'open_chat_id' => 3001,
                'category' => 1,
                'type' => 'ranking',
                'open_position' => 20,
                'high_position' => 15,
                'low_position' => 25,
                'close_position' => 18,
                'date' => '2025-01-15',
            ],
        ];

        $this->repository->insertOhlc($data);
        SQLiteRankingPositionOhlc::$pdo = null;

        // category=0, type=ranking のみ取得
        $result = $this->repository->getOhlcDateAsc(3001, 0, RankingType::Ranking);
        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['open_position']);

        // category=0, type=rising のみ取得
        $result2 = $this->repository->getOhlcDateAsc(3001, 0, RankingType::Rising);
        $this->assertCount(1, $result2);
        $this->assertEquals(10, $result2[0]['open_position']);

        // category=1, type=ranking のみ取得
        $result3 = $this->repository->getOhlcDateAsc(3001, 1, RankingType::Ranking);
        $this->assertCount(1, $result3);
        $this->assertEquals(20, $result3[0]['open_position']);
    }

    /**
     * @test insertOhlc に空配列を渡すと0件が返ること
     */
    public function testInsertOhlcEmpty(): void
    {
        $count = $this->repository->insertOhlc([]);
        $this->assertSame(0, $count);
    }

    /**
     * @test 存在しない open_chat_id を指定すると空配列を返すこと
     */
    public function testGetOhlcDateAscEmpty(): void
    {
        // DBファイルを作成するためにwrite-modeで一度connectする
        SQLiteRankingPositionOhlc::connect();
        SQLiteRankingPositionOhlc::$pdo = null;

        $result = $this->repository->getOhlcDateAsc(99999, 0, RankingType::Ranking);
        $this->assertSame([], $result);
    }

    /**
     * @test 複数日のデータが日付昇順でソートされて返ること
     * - 挿入順が降順でも結果は昇順
     */
    public function testGetOhlcDateAscSortedByDate(): void
    {
        $data = [
            [
                'open_chat_id' => 4001,
                'category' => 0,
                'type' => 'ranking',
                'open_position' => 5,
                'high_position' => 3,
                'low_position' => 8,
                'close_position' => 4,
                'date' => '2025-01-17',
            ],
            [
                'open_chat_id' => 4001,
                'category' => 0,
                'type' => 'ranking',
                'open_position' => 4,
                'high_position' => 2,
                'low_position' => 6,
                'close_position' => 3,
                'date' => '2025-01-15',
            ],
            [
                'open_chat_id' => 4001,
                'category' => 0,
                'type' => 'ranking',
                'open_position' => 3,
                'high_position' => 1,
                'low_position' => 5,
                'close_position' => 2,
                'date' => '2025-01-16',
            ],
        ];

        $this->repository->insertOhlc($data);
        SQLiteRankingPositionOhlc::$pdo = null;

        $result = $this->repository->getOhlcDateAsc(4001, 0, RankingType::Ranking);

        $this->assertCount(3, $result);
        $this->assertSame('2025-01-15', $result[0]['date']);
        $this->assertSame('2025-01-16', $result[1]['date']);
        $this->assertSame('2025-01-17', $result[2]['date']);
    }
}

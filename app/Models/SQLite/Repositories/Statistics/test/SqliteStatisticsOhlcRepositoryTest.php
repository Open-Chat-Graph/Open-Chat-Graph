<?php

/**
 * SqliteStatisticsOhlcRepositoryのテスト
 *
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Models/SQLite/Repositories/Statistics/test/SqliteStatisticsOhlcRepositoryTest.php
 *
 * テスト対象メソッド:
 * - insertOhlc() - OHLCデータの挿入
 * - getOhlcDateAsc() - OHLCデータの日付昇順取得
 */

declare(strict_types=1);

use App\Models\SQLite\Repositories\Statistics\SqliteStatisticsOhlcRepository;
use App\Models\SQLite\SQLiteStatisticsOhlc;
use App\Services\Storage\FileStorageInterface;
use Shadow\Kernel\Dispatcher\ConstructorInjection;
use PHPUnit\Framework\TestCase;

class SqliteStatisticsOhlcRepositoryTest extends TestCase
{
    private SqliteStatisticsOhlcRepository $repository;
    private string $tempDir;

    private mixed $originalStorage;

    protected function setUp(): void
    {
        // 一時ディレクトリを作成
        $this->tempDir = sys_get_temp_dir() . '/test_statistics_ohlc_' . uniqid();
        mkdir($this->tempDir . '/SQLite/statistics_ohlc', 0777, true);

        // 元のFileStorageInterfaceを保存
        $this->originalStorage = ConstructorInjection::$container[FileStorageInterface::class] ?? null;

        // FileStorageInterfaceモックを作成しDIコンテナに登録
        $dbPath = $this->tempDir . '/SQLite/statistics_ohlc/statistics_ohlc.db';
        $mockStorage = $this->createMock(FileStorageInterface::class);
        $mockStorage->method('getStorageFilePath')
            ->willReturnCallback(fn(string $key) => match ($key) {
                'sqliteStatisticsOhlcDb' => $dbPath,
                default => '',
            });

        ConstructorInjection::$container[FileStorageInterface::class] = [
            'concrete' => $mockStorage,
            'singleton' => ['flag' => true],
        ];

        // 静的PDOをリセットしてDB生成から実行されるようにする
        SQLiteStatisticsOhlc::$pdo = null;

        $this->repository = app(SqliteStatisticsOhlcRepository::class);
    }

    protected function tearDown(): void
    {
        SQLiteStatisticsOhlc::$pdo = null;

        // 一時ファイルを削除
        $dbFile = $this->tempDir . '/SQLite/statistics_ohlc/statistics_ohlc.db';
        foreach (glob($dbFile . '*') as $f) {
            @unlink($f);
        }
        @rmdir($this->tempDir . '/SQLite/statistics_ohlc');
        @rmdir($this->tempDir . '/SQLite');
        @rmdir($this->tempDir);

        // DIコンテナを復元
        if ($this->originalStorage !== null) {
            ConstructorInjection::$container[FileStorageInterface::class] = $this->originalStorage;
        } else {
            unset(ConstructorInjection::$container[FileStorageInterface::class]);
        }
    }

    /**
     * @test insertOhlc → getOhlcDateAsc の基本フロー
     * - 1件挿入して1件返ること
     * - date, open_member, high_member, low_member, close_member が一致すること
     */
    public function testInsertOhlcAndGetOhlcDateAsc(): void
    {
        $data = [
            [
                'open_chat_id' => 1001,
                'open_member' => 100,
                'high_member' => 120,
                'low_member' => 95,
                'close_member' => 110,
                'date' => '2025-01-15',
            ],
        ];

        $count = $this->repository->insertOhlc($data);
        $this->assertSame(1, $count, '1件挿入されること');

        // PDOをリセットしてread-onlyモードで取得
        SQLiteStatisticsOhlc::$pdo = null;

        $result = $this->repository->getOhlcDateAsc(1001);

        $this->assertCount(1, $result);
        $this->assertSame('2025-01-15', $result[0]['date']);
        $this->assertEquals(100, $result[0]['open_member']);
        $this->assertEquals(120, $result[0]['high_member']);
        $this->assertEquals(95, $result[0]['low_member']);
        $this->assertEquals(110, $result[0]['close_member']);
    }

    /**
     * @test insertOhlc に空配列を渡すと0件が返ること
     */
    public function testInsertOhlcEmpty(): void
    {
        $count = $this->repository->insertOhlc([]);
        $this->assertSame(0, $count, '空配列は0件');
    }

    /**
     * @test 存在しない open_chat_id を指定すると空配列を返すこと
     */
    public function testGetOhlcDateAscEmpty(): void
    {
        // DBファイルを作成するためにwrite-modeで一度connectする
        SQLiteStatisticsOhlc::connect();
        SQLiteStatisticsOhlc::$pdo = null;

        $result = $this->repository->getOhlcDateAsc(99999);
        $this->assertSame([], $result, '存在しないIDは空配列');
    }

    /**
     * @test 複数日のデータが日付昇順でソートされて返ること
     * - 挿入順が降順でも結果は昇順
     */
    public function testGetOhlcDateAscMultipleDaysSortedAsc(): void
    {
        $data = [
            [
                'open_chat_id' => 2001,
                'open_member' => 200,
                'high_member' => 250,
                'low_member' => 190,
                'close_member' => 240,
                'date' => '2025-01-17',
            ],
            [
                'open_chat_id' => 2001,
                'open_member' => 240,
                'high_member' => 260,
                'low_member' => 230,
                'close_member' => 255,
                'date' => '2025-01-15',
            ],
            [
                'open_chat_id' => 2001,
                'open_member' => 255,
                'high_member' => 270,
                'low_member' => 250,
                'close_member' => 265,
                'date' => '2025-01-16',
            ],
        ];

        $this->repository->insertOhlc($data);
        SQLiteStatisticsOhlc::$pdo = null;

        $result = $this->repository->getOhlcDateAsc(2001);

        $this->assertCount(3, $result);
        $this->assertSame('2025-01-15', $result[0]['date']);
        $this->assertSame('2025-01-16', $result[1]['date']);
        $this->assertSame('2025-01-17', $result[2]['date']);
    }

    /**
     * @test 異なる open_chat_id のデータが混在しても指定IDのみ返すこと
     */
    public function testGetOhlcDateAscFiltersByOpenChatId(): void
    {
        $data = [
            [
                'open_chat_id' => 3001,
                'open_member' => 100,
                'high_member' => 110,
                'low_member' => 90,
                'close_member' => 105,
                'date' => '2025-01-15',
            ],
            [
                'open_chat_id' => 3002,
                'open_member' => 500,
                'high_member' => 550,
                'low_member' => 480,
                'close_member' => 520,
                'date' => '2025-01-15',
            ],
        ];

        $this->repository->insertOhlc($data);
        SQLiteStatisticsOhlc::$pdo = null;

        $result = $this->repository->getOhlcDateAsc(3001);
        $this->assertCount(1, $result);
        $this->assertEquals(100, $result[0]['open_member']);

        $result2 = $this->repository->getOhlcDateAsc(3002);
        $this->assertCount(1, $result2);
        $this->assertEquals(500, $result2[0]['open_member']);
    }

    /**
     * @test 同じ open_chat_id + date の重複挿入は INSERT OR IGNORE で無視されること
     * - 2回目の挿入後も1件のみ、最初の値が保持される
     */
    public function testInsertOhlcDuplicateDateIgnored(): void
    {
        $data = [
            [
                'open_chat_id' => 4001,
                'open_member' => 100,
                'high_member' => 120,
                'low_member' => 90,
                'close_member' => 110,
                'date' => '2025-01-15',
            ],
        ];

        $this->repository->insertOhlc($data);

        // 同じopen_chat_id + dateで異なる値を挿入
        $duplicateData = [
            [
                'open_chat_id' => 4001,
                'open_member' => 999,
                'high_member' => 999,
                'low_member' => 999,
                'close_member' => 999,
                'date' => '2025-01-15',
            ],
        ];

        $this->repository->insertOhlc($duplicateData);
        SQLiteStatisticsOhlc::$pdo = null;

        $result = $this->repository->getOhlcDateAsc(4001);
        $this->assertCount(1, $result, '重複は無視されて1件のみ');
        $this->assertEquals(100, $result[0]['open_member'], '最初に挿入された値が保持される');
    }
}

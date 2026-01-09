<?php

/**
 * SqliteStatisticsRepositoryのテスト
 *
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Models/SQLite/Repositories/Statistics/test/SqliteStatisticsRepositoryTest.php
 *
 * テスト対象メソッド:
 * - getNewRoomsWithLessThan8Records() - レコード数が8以下の新規部屋
 * - getMemberChangeWithinLastWeek() - 過去8日間でメンバー数が変動した部屋
 * - getWeeklyUpdateRooms() - 最後のレコードが1週間以上前の部屋
 */

declare(strict_types=1);

use App\Models\SQLite\Repositories\Statistics\SqliteStatisticsRepository;
use App\Models\SQLite\SQLiteStatistics;
use PHPUnit\Framework\TestCase;
class SqliteStatisticsRepositoryTest extends TestCase
{
    private SqliteStatisticsRepository $repository;
    private string $tempDbFile = '';
    private string $today;

    /**
     * 各テストの前に実行: 一時的なテスト用SQLiteデータベースを構築
     */
    protected function setUp(): void
    {
        // 一時的なテスト用SQLiteファイルを作成
        $this->tempDbFile = sys_get_temp_dir() . '/test_statistics_' . uniqid() . '.db';

        // テスト用PDOインスタンスを作成してSQLiteStatistics::$pdoにセット
        SQLiteStatistics::$pdo = new \PDO('sqlite:' . $this->tempDbFile);
        SQLiteStatistics::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // テーブル作成（本番環境と同じ構造）
        SQLiteStatistics::$pdo->exec("
            CREATE TABLE statistics (
                open_chat_id INTEGER NOT NULL,
                member INTEGER NOT NULL,
                date TEXT NOT NULL,
                PRIMARY KEY (open_chat_id, date)
            )
        ");

        // テストデータ挿入
        $this->today = date('Y-m-d');
        $this->insertTestData();

        // リポジトリインスタンス作成
        $this->repository = app(SqliteStatisticsRepository::class);
    }

    /**
     * 各テストの後に実行: 一時ファイルを削除してPDOを復元
     */
    protected function tearDown(): void
    {
        // テスト用SQLiteファイルを削除
        if (file_exists($this->tempDbFile)) {
            unlink($this->tempDbFile);
        }
    }

    /**
     * テストデータを挿入
     */
    private function insertTestData(): void
    {
        $testData = [
            // パターン1: メンバー数が変動している部屋（ID: 1001）
            // 過去8日間で100→120と増加
            [1001, 100, date('Y-m-d', strtotime('-8 days'))],
            [1001, 105, date('Y-m-d', strtotime('-7 days'))],
            [1001, 110, date('Y-m-d', strtotime('-6 days'))],
            [1001, 115, date('Y-m-d', strtotime('-5 days'))],
            [1001, 115, date('Y-m-d', strtotime('-4 days'))],
            [1001, 120, date('Y-m-d', strtotime('-3 days'))],
            [1001, 120, date('Y-m-d', strtotime('-2 days'))],
            [1001, 120, date('Y-m-d', strtotime('-1 days'))],
            [1001, 120, $this->today],

            // パターン2: メンバー数が変動していない部屋（ID: 1002）
            // 過去8日間ずっと200のまま（対象外）
            [1002, 200, date('Y-m-d', strtotime('-8 days'))],
            [1002, 200, date('Y-m-d', strtotime('-7 days'))],
            [1002, 200, date('Y-m-d', strtotime('-6 days'))],
            [1002, 200, date('Y-m-d', strtotime('-5 days'))],
            [1002, 200, date('Y-m-d', strtotime('-4 days'))],
            [1002, 200, date('Y-m-d', strtotime('-3 days'))],
            [1002, 200, date('Y-m-d', strtotime('-2 days'))],
            [1002, 200, date('Y-m-d', strtotime('-1 days'))],
            [1002, 200, $this->today],

            // パターン3: レコード数が8以下の新規部屋（ID: 1003）
            // 最近追加されたため5日分しかレコードがない
            [1003, 50, date('Y-m-d', strtotime('-4 days'))],
            [1003, 55, date('Y-m-d', strtotime('-3 days'))],
            [1003, 60, date('Y-m-d', strtotime('-2 days'))],
            [1003, 65, date('Y-m-d', strtotime('-1 days'))],
            [1003, 70, $this->today],

            // パターン4: 最終更新が1週間以上前の部屋（ID: 1004）
            // 最終レコードが8日前（対象）
            [1004, 300, date('Y-m-d', strtotime('-15 days'))],
            [1004, 310, date('Y-m-d', strtotime('-14 days'))],
            [1004, 320, date('Y-m-d', strtotime('-13 days'))],
            [1004, 330, date('Y-m-d', strtotime('-12 days'))],
            [1004, 340, date('Y-m-d', strtotime('-11 days'))],
            [1004, 350, date('Y-m-d', strtotime('-10 days'))],
            [1004, 360, date('Y-m-d', strtotime('-9 days'))],
            [1004, 370, date('Y-m-d', strtotime('-8 days'))],

            // パターン5: 通常の部屋（対象外）（ID: 1005）
            // レコード数が8以上、メンバー変動なし、最終更新が1週間以内
            [1005, 500, date('Y-m-d', strtotime('-8 days'))],
            [1005, 500, date('Y-m-d', strtotime('-7 days'))],
            [1005, 500, date('Y-m-d', strtotime('-6 days'))],
            [1005, 500, date('Y-m-d', strtotime('-5 days'))],
            [1005, 500, date('Y-m-d', strtotime('-4 days'))],
            [1005, 500, date('Y-m-d', strtotime('-3 days'))],
            [1005, 500, date('Y-m-d', strtotime('-2 days'))],
            [1005, 500, date('Y-m-d', strtotime('-1 days'))],
            [1005, 500, $this->today],

            // パターン6: 最近1日だけメンバー変動（ID: 1006）
            [1006, 600, date('Y-m-d', strtotime('-8 days'))],
            [1006, 600, date('Y-m-d', strtotime('-7 days'))],
            [1006, 600, date('Y-m-d', strtotime('-6 days'))],
            [1006, 600, date('Y-m-d', strtotime('-5 days'))],
            [1006, 600, date('Y-m-d', strtotime('-4 days'))],
            [1006, 600, date('Y-m-d', strtotime('-3 days'))],
            [1006, 600, date('Y-m-d', strtotime('-2 days'))],
            [1006, 610, date('Y-m-d', strtotime('-1 days'))],
            [1006, 610, $this->today],
        ];

        $stmt = SQLiteStatistics::$pdo->prepare(
            "INSERT INTO statistics (open_chat_id, member, date) VALUES (?, ?, ?)"
        );

        foreach ($testData as $row) {
            $stmt->execute($row);
        }
    }

    /**
     * テスト: getNewRoomsWithLessThan8Records()
     *
     * 期待される動作:
     * - レコード数が8以下の部屋のみを取得
     * - ID 1003のみが該当（レコード数5件）
     */
    public function testGetNewRoomsWithLessThan8Records(): void
    {
        $result = $this->repository->getNewRoomsWithLessThan8Records();
        sort($result);

        $expected = [1003];

        $this->assertSame(
            $expected,
            $result,
            'レコード数が8以下の新規部屋（ID: 1003）のみを取得すること'
        );
    }

    /**
     * テスト: getMemberChangeWithinLastWeek()
     *
     * 期待される動作:
     * - 過去8日間でメンバー数が変動した部屋のみを取得
     * - ID 1001, 1003, 1006が該当
     *   - 1001: 100→105→110→115→115→120→120→120→120
     *   - 1003: 50→55→60→65→70（新規部屋だがメンバー変動あり）
     *   - 1006: 600→600→600→600→600→600→600→610→610
     */
    public function testGetMemberChangeWithinLastWeek(): void
    {
        $result = $this->repository->getMemberChangeWithinLastWeek($this->today);
        sort($result);

        $expected = [1001, 1003, 1006];

        $this->assertSame(
            $expected,
            $result,
            '過去8日間でメンバー数が変動した部屋を取得すること'
        );
    }

    /**
     * テスト: getWeeklyUpdateRooms()
     *
     * 期待される動作:
     * - 最後のレコードが1週間以上前の部屋を取得
     * - ID 1004のみが該当
     */
    public function testGetWeeklyUpdateRooms(): void
    {
        $result = $this->repository->getWeeklyUpdateRooms($this->today);
        sort($result);

        $expected = [1004];

        $this->assertSame(
            $expected,
            $result,
            '最後のレコードが1週間以上前の部屋（ID: 1004）のみを取得すること'
        );
    }

    /**
     * テスト: 3つのメソッドを組み合わせた場合
     *
     * 期待される動作:
     * - getMemberChangeWithinLastWeek + getNewRoomsWithLessThan8Records + getWeeklyUpdateRooms
     * - = ID 1001, 1003, 1004, 1006
     */
    public function testCombinedMethods(): void
    {
        $memberChange = $this->repository->getMemberChangeWithinLastWeek($this->today);
        $newRooms = $this->repository->getNewRoomsWithLessThan8Records();
        $weeklyUpdate = $this->repository->getWeeklyUpdateRooms($this->today);

        $combined = array_unique(array_merge($memberChange, $newRooms, $weeklyUpdate));
        sort($combined);

        $expected = [1001, 1003, 1004, 1006];

        $this->assertSame(
            $expected,
            $combined,
            '3つのメソッドを組み合わせると全対象部屋を取得できること'
        );
    }

    /**
     * テスト: 各テストケースのレコード数を検証
     *
     * 各部屋のレコード数が期待通りであることを確認
     */
    public function testRecordCounts(): void
    {
        $stmt = SQLiteStatistics::$pdo->query("
            SELECT open_chat_id, COUNT(*) as count
            FROM statistics
            GROUP BY open_chat_id
            ORDER BY open_chat_id
        ");
        $counts = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        // 各部屋のレコード数を検証
        $this->assertSame(9, $counts[1001], '部屋1001は9件のレコードを持つこと');
        $this->assertSame(9, $counts[1002], '部屋1002は9件のレコードを持つこと');
        $this->assertSame(5, $counts[1003], '部屋1003は5件のレコードを持つこと（新規部屋）');
        $this->assertSame(8, $counts[1004], '部屋1004は8件のレコードを持つこと');
        $this->assertSame(9, $counts[1005], '部屋1005は9件のレコードを持つこと');
        $this->assertSame(9, $counts[1006], '部屋1006は9件のレコードを持つこと');
    }

    /**
     * テスト: 各テストケースのメンバー変動を検証
     *
     * 各部屋のメンバー変動が期待通りであることを確認
     */
    public function testMemberChanges(): void
    {
        $stmt = SQLiteStatistics::$pdo->query("
            SELECT
                open_chat_id,
                COUNT(DISTINCT member) as distinct_count
            FROM statistics
            GROUP BY open_chat_id
            ORDER BY open_chat_id
        ");
        $changes = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        // 各部屋のメンバー変動を検証
        $this->assertGreaterThan(1, $changes[1001], '部屋1001はメンバー数が変動していること');
        $this->assertSame(1, $changes[1002], '部屋1002はメンバー数が変動していないこと');
        $this->assertGreaterThan(1, $changes[1003], '部屋1003はメンバー数が変動していること');
        $this->assertGreaterThan(1, $changes[1004], '部屋1004はメンバー数が変動していること');
        $this->assertSame(1, $changes[1005], '部屋1005はメンバー数が変動していないこと');
        $this->assertGreaterThan(1, $changes[1006], '部屋1006はメンバー数が変動していること');
    }

    /**
     * テスト: 空のデータベースでの動作確認
     *
     * データが存在しない場合、空配列を返すことを確認
     */
    public function testEmptyDatabase(): void
    {
        // データをすべて削除
        SQLiteStatistics::$pdo->exec("DELETE FROM statistics");

        // 各メソッドが空配列を返すことを確認
        $this->assertSame(
            [],
            $this->repository->getNewRoomsWithLessThan8Records(),
            'データが存在しない場合、空配列を返すこと'
        );

        $this->assertSame(
            [],
            $this->repository->getMemberChangeWithinLastWeek($this->today),
            'データが存在しない場合、空配列を返すこと'
        );

        $this->assertSame(
            [],
            $this->repository->getWeeklyUpdateRooms($this->today),
            'データが存在しない場合、空配列を返すこと'
        );
    }
}

<?php

use App\Services\Cron\OcreviewApiCommentDataImporter;
use PHPUnit\Framework\TestCase;

/**
 * OcreviewApiDataImporterのコメントデータ同期テスト
 *
 * 【テスト内容】
 * 1. commentテーブルの新規INSERT（IDベース）
 * 2. commentテーブルのflag差分同期
 * 3. likeテーブルのID配列比較による完全同期（追加・削除）
 * 4. ban_roomテーブルのIDベース同期
 * 5. ban_userテーブルのIDベース同期
 * 6. logテーブルのIDベース同期
 * 7. テーブル自動作成機能
 *
 * 【実行コマンド】
 * docker compose exec app ./vendor/bin/phpunit app/Services/Cron/test/OcreviewApiDataImporterCommentTest.php
 */
class OcreviewApiDataImporterCommentTest extends TestCase
{
    private OcreviewApiCommentDataImporter $importer;
    private PDO $mockSourceCommentPdo;
    private PDO $mockTargetPdo;

    protected function setUp(): void
    {
        parent::setUp();

        // モックDBをセットアップ
        $this->setupMockDatabases();

        // Importerインスタンス作成
        $this->importer = new OcreviewApiCommentDataImporter($this->mockSourceCommentPdo, $this->mockTargetPdo);
    }

    private function setupMockDatabases(): void
    {
        // ソースDB（MySQL: ocgraph_comment）
        $this->mockSourceCommentPdo = new PDO('sqlite::memory:');
        $this->setupSourceCommentSchema($this->mockSourceCommentPdo);

        // ターゲットDB（SQLite: ocgraph_sqlapi）
        $this->mockTargetPdo = new PDO('sqlite::memory:');
        $this->setupTargetSchema($this->mockTargetPdo);
    }

    private function setupSourceCommentSchema(PDO $pdo): void
    {
        // MySQL: ocgraph_commentのスキーマ
        $pdo->exec("
            CREATE TABLE comment (
                comment_id INTEGER PRIMARY KEY,
                open_chat_id INTEGER NOT NULL,
                id INTEGER NOT NULL,
                user_id TEXT NOT NULL,
                name TEXT NOT NULL,
                text TEXT NOT NULL,
                time TEXT NOT NULL,
                flag INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE `like` (
                id INTEGER PRIMARY KEY,
                comment_id INTEGER NOT NULL,
                user_id TEXT NOT NULL,
                type TEXT NOT NULL,
                time TEXT NOT NULL
            );

            CREATE TABLE ban_room (
                id INTEGER PRIMARY KEY,
                open_chat_id INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                type INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE ban_user (
                id INTEGER PRIMARY KEY,
                user_id TEXT NOT NULL,
                ip TEXT NOT NULL,
                created_at TEXT NOT NULL,
                type INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE log (
                id INTEGER PRIMARY KEY,
                entity_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                data TEXT NOT NULL,
                ip TEXT NOT NULL,
                ua TEXT NOT NULL
            );
        ");
    }

    private function setupTargetSchema(PDO $pdo): void
    {
        // 一部のテストで初期データを挿入するため、テーブルを作成
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS comment (
                comment_id INTEGER PRIMARY KEY,
                open_chat_id INTEGER NOT NULL,
                id INTEGER NOT NULL,
                user_id TEXT NOT NULL,
                name TEXT NOT NULL,
                text TEXT NOT NULL,
                time TEXT NOT NULL,
                flag INTEGER NOT NULL DEFAULT 0
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS comment_like (
                id INTEGER PRIMARY KEY,
                comment_id INTEGER NOT NULL,
                user_id TEXT NOT NULL,
                type TEXT NOT NULL,
                time TEXT NOT NULL
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ban_room (
                id INTEGER PRIMARY KEY,
                open_chat_id INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                type INTEGER NOT NULL DEFAULT 0
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ban_user (
                id INTEGER PRIMARY KEY,
                user_id TEXT NOT NULL,
                ip TEXT NOT NULL,
                created_at TEXT NOT NULL,
                type INTEGER NOT NULL DEFAULT 0
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS comment_log (
                id INTEGER PRIMARY KEY,
                entity_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                data TEXT NOT NULL,
                ip TEXT NOT NULL,
                ua TEXT NOT NULL
            )
        ");
    }

    /**
     * commentテーブルの新規INSERT（IDベース）
     */
    public function testImportCommentsInsert(): void
    {
        // ソースDBにテストデータを挿入
        $this->mockSourceCommentPdo->exec("
            INSERT INTO comment (comment_id, open_chat_id, id, user_id, name, text, time, flag)
            VALUES
            (1, 100, 1, 'user1', 'User One', 'Test comment 1', '2024-01-01 00:00:00', 0),
            (2, 100, 2, 'user2', 'User Two', 'Test comment 2', '2024-01-02 00:00:00', 0),
            (3, 200, 3, 'user3', 'User Three', 'Test comment 3', '2024-01-03 00:00:00', 1)
        ");

        // インポート実行
        $this->importer->execute();

        // ターゲットDBにデータが入っているか確認
        $result = $this->mockTargetPdo->query("SELECT * FROM comment ORDER BY comment_id")->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(3, $result);
        $this->assertEquals(1, $result[0]['comment_id']);
        $this->assertEquals('Test comment 1', $result[0]['text']);
        $this->assertEquals(0, $result[0]['flag']);

        $this->assertEquals(3, $result[2]['comment_id']);
        $this->assertEquals('Test comment 3', $result[2]['text']);
        $this->assertEquals(1, $result[2]['flag']);
    }

    /**
     * commentテーブルのflag差分同期
     */
    public function testSyncCommentFlags(): void
    {
        // ターゲットDBに初期データを挿入
        $this->mockTargetPdo->exec("
            INSERT INTO comment (comment_id, open_chat_id, id, user_id, name, text, time, flag)
            VALUES
            (1, 100, 1, 'user1', 'User One', 'Test comment 1', '2024-01-01 00:00:00', 0),
            (2, 100, 2, 'user2', 'User Two', 'Test comment 2', '2024-01-02 00:00:00', 0),
            (3, 200, 3, 'user3', 'User Three', 'Test comment 3', '2024-01-03 00:00:00', 0)
        ");

        // ソースDBにflagが変更されたデータを挿入
        $this->mockSourceCommentPdo->exec("
            INSERT INTO comment (comment_id, open_chat_id, id, user_id, name, text, time, flag)
            VALUES
            (1, 100, 1, 'user1', 'User One', 'Test comment 1', '2024-01-01 00:00:00', 0),
            (2, 100, 2, 'user2', 'User Two', 'Test comment 2', '2024-01-02 00:00:00', 1),
            (3, 200, 3, 'user3', 'User Three', 'Test comment 3', '2024-01-03 00:00:00', 2)
        ");

        // インポート実行
        $this->importer->execute();

        // flagが更新されているか確認
        $result = $this->mockTargetPdo->query("SELECT comment_id, flag FROM comment ORDER BY comment_id")->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEquals(0, $result[0]['flag']); // 変更なし
        $this->assertEquals(1, $result[1]['flag']); // 0 → 1
        $this->assertEquals(2, $result[2]['flag']); // 0 → 2
    }

    /**
     * commentテーブルの新規INSERT + flag差分同期の混在
     */
    public function testImportCommentsWithMixedOperations(): void
    {
        // ターゲットDBに既存データを挿入
        $this->mockTargetPdo->exec("
            INSERT INTO comment (comment_id, open_chat_id, id, user_id, name, text, time, flag)
            VALUES
            (1, 100, 1, 'user1', 'User One', 'Test comment 1', '2024-01-01 00:00:00', 0),
            (2, 100, 2, 'user2', 'User Two', 'Test comment 2', '2024-01-02 00:00:00', 0)
        ");

        // ソースDB
        // - comment_id 1, 2: 既存（flagのみ変更）
        // - comment_id 3, 4: 新規追加
        $this->mockSourceCommentPdo->exec("
            INSERT INTO comment (comment_id, open_chat_id, id, user_id, name, text, time, flag)
            VALUES
            (1, 100, 1, 'user1', 'User One', 'Test comment 1', '2024-01-01 00:00:00', 1),
            (2, 100, 2, 'user2', 'User Two', 'Test comment 2', '2024-01-02 00:00:00', 0),
            (3, 200, 3, 'user3', 'User Three', 'Test comment 3', '2024-01-03 00:00:00', 0),
            (4, 200, 4, 'user4', 'User Four', 'Test comment 4', '2024-01-04 00:00:00', 1)
        ");

        // インポート実行
        $this->importer->execute();

        // レコード数が4件
        $count = $this->mockTargetPdo->query("SELECT COUNT(*) FROM comment")->fetchColumn();
        $this->assertEquals(4, $count);

        // flagが更新されている
        $result = $this->mockTargetPdo->query("SELECT comment_id, flag FROM comment ORDER BY comment_id")->fetchAll(PDO::FETCH_ASSOC);
        $this->assertEquals(1, $result[0]['flag']); // 0 → 1
        $this->assertEquals(0, $result[1]['flag']); // 変更なし

        // 新規レコードが追加されている
        $this->assertEquals(3, $result[2]['comment_id']);
        $this->assertEquals(0, $result[2]['flag']);
        $this->assertEquals(4, $result[3]['comment_id']);
        $this->assertEquals(1, $result[3]['flag']);
    }

    /**
     * likeテーブルのID配列比較による完全同期（追加のみ）
     */
    public function testImportCommentLikesInsert(): void
    {
        // ソースDBにテストデータを挿入
        $this->mockSourceCommentPdo->exec("
            INSERT INTO `like` (id, comment_id, user_id, type, time)
            VALUES
            (1, 1, 'user1', 'like', '2024-01-01 00:00:00'),
            (2, 1, 'user2', 'like', '2024-01-02 00:00:00'),
            (3, 2, 'user3', 'dislike', '2024-01-03 00:00:00')
        ");

        // インポート実行
        $this->importer->execute();

        // ターゲットDBにデータが入っているか確認
        $result = $this->mockTargetPdo->query("SELECT * FROM comment_like ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(3, $result);
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals(1, $result[0]['comment_id']);
        $this->assertEquals('user1', $result[0]['user_id']);
        $this->assertEquals('like', $result[0]['type']);
    }

    /**
     * likeテーブルのID配列比較による完全同期（削除）
     */
    public function testImportCommentLikesDelete(): void
    {
        // ターゲットDBに初期データを挿入
        $this->mockTargetPdo->exec("
            INSERT INTO comment_like (id, comment_id, user_id, type, time)
            VALUES
            (1, 1, 'user1', 'like', '2024-01-01 00:00:00'),
            (2, 1, 'user2', 'like', '2024-01-02 00:00:00'),
            (3, 2, 'user3', 'dislike', '2024-01-03 00:00:00')
        ");

        // ソースDBには一部のいいねが削除されている
        $this->mockSourceCommentPdo->exec("
            INSERT INTO `like` (id, comment_id, user_id, type, time)
            VALUES
            (1, 1, 'user1', 'like', '2024-01-01 00:00:00'),
            (3, 2, 'user3', 'dislike', '2024-01-03 00:00:00')
        ");

        // インポート実行
        $this->importer->execute();

        // ターゲットDBから削除されているか確認
        $result = $this->mockTargetPdo->query("SELECT id FROM comment_like ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

        $this->assertCount(2, $result);
        $this->assertEquals([1, 3], $result);
    }

    /**
     * likeテーブルのID配列比較による完全同期（追加 + 削除）
     */
    public function testImportCommentLikesMixed(): void
    {
        // ターゲットDBに初期データを挿入
        $this->mockTargetPdo->exec("
            INSERT INTO comment_like (id, comment_id, user_id, type, time)
            VALUES
            (1, 1, 'user1', 'like', '2024-01-01 00:00:00'),
            (2, 1, 'user2', 'like', '2024-01-02 00:00:00'),
            (3, 2, 'user3', 'dislike', '2024-01-03 00:00:00')
        ");

        // ソースDB
        // - id 1: 保持
        // - id 2: 削除（いいね取り消し）
        // - id 3: 削除（いいね取り消し）
        // - id 4, 5: 追加
        $this->mockSourceCommentPdo->exec("
            INSERT INTO `like` (id, comment_id, user_id, type, time)
            VALUES
            (1, 1, 'user1', 'like', '2024-01-01 00:00:00'),
            (4, 3, 'user4', 'like', '2024-01-04 00:00:00'),
            (5, 3, 'user5', 'dislike', '2024-01-05 00:00:00')
        ");

        // インポート実行
        $this->importer->execute();

        // ターゲットDBが正しく同期されているか確認
        $result = $this->mockTargetPdo->query("SELECT id FROM comment_like ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

        $this->assertCount(3, $result);
        $this->assertEquals([1, 4, 5], $result);
    }

    /**
     * ban_roomテーブルのIDベース同期
     */
    public function testImportBanRooms(): void
    {
        // ソースDBにテストデータを挿入
        $this->mockSourceCommentPdo->exec("
            INSERT INTO ban_room (id, open_chat_id, created_at, type)
            VALUES
            (1, 100, '2024-01-01 00:00:00', 0),
            (2, 200, '2024-01-02 00:00:00', 1)
        ");

        // インポート実行
        $this->importer->execute();

        // ターゲットDBにデータが入っているか確認
        $result = $this->mockTargetPdo->query("SELECT * FROM ban_room ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals(100, $result[0]['open_chat_id']);
        $this->assertEquals(2, $result[1]['id']);
        $this->assertEquals(200, $result[1]['open_chat_id']);
    }

    /**
     * ban_roomテーブルの差分同期
     */
    public function testImportBanRoomsDifferential(): void
    {
        // ターゲットDBに初期データを挿入
        $this->mockTargetPdo->exec("
            INSERT INTO ban_room (id, open_chat_id, created_at, type)
            VALUES
            (1, 100, '2024-01-01 00:00:00', 0)
        ");

        // ソースDBに新しいデータを追加
        $this->mockSourceCommentPdo->exec("
            INSERT INTO ban_room (id, open_chat_id, created_at, type)
            VALUES
            (1, 100, '2024-01-01 00:00:00', 0),
            (2, 200, '2024-01-02 00:00:00', 1),
            (3, 300, '2024-01-03 00:00:00', 0)
        ");

        // インポート実行
        $this->importer->execute();

        // 新しいレコードのみが追加されているか確認
        $result = $this->mockTargetPdo->query("SELECT id FROM ban_room ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

        $this->assertCount(3, $result);
        $this->assertEquals([1, 2, 3], $result);
    }

    /**
     * ban_userテーブルのIDベース同期
     */
    public function testImportBanUsers(): void
    {
        // ソースDBにテストデータを挿入
        $this->mockSourceCommentPdo->exec("
            INSERT INTO ban_user (id, user_id, ip, created_at, type)
            VALUES
            (1, 'user1', '192.168.1.1', '2024-01-01 00:00:00', 0),
            (2, 'user2', '192.168.1.2', '2024-01-02 00:00:00', 1)
        ");

        // インポート実行
        $this->importer->execute();

        // ターゲットDBにデータが入っているか確認
        $result = $this->mockTargetPdo->query("SELECT * FROM ban_user ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals('user1', $result[0]['user_id']);
        $this->assertEquals('192.168.1.1', $result[0]['ip']);
    }

    /**
     * logテーブルのIDベース同期
     */
    public function testImportCommentLogs(): void
    {
        // ソースDBにテストデータを挿入
        $this->mockSourceCommentPdo->exec("
            INSERT INTO log (id, entity_id, type, data, ip, ua)
            VALUES
            (1, 100, 'comment_create', '{\"comment_id\": 1}', '192.168.1.1', 'Mozilla/5.0'),
            (2, 101, 'comment_update', '{\"comment_id\": 2}', '192.168.1.2', 'Mozilla/5.0')
        ");

        // インポート実行
        $this->importer->execute();

        // ターゲットDBにデータが入っているか確認
        $result = $this->mockTargetPdo->query("SELECT * FROM comment_log ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals('comment_create', $result[0]['type']);
        $this->assertEquals(2, $result[1]['id']);
        $this->assertEquals('comment_update', $result[1]['type']);
    }

    /**
     * テーブル自動作成機能のテスト
     */
    public function testEnsureCommentTablesExist(): void
    {
        // 新しいターゲットDBを作成（テーブルなし）
        $newTargetPdo = new PDO('sqlite::memory:');

        // 新しいimporterインスタンスを作成
        $newImporter = new OcreviewApiCommentDataImporter($this->mockSourceCommentPdo, $newTargetPdo);

        // ソースDBにダミーデータを挿入
        $this->mockSourceCommentPdo->exec("
            INSERT INTO comment (comment_id, open_chat_id, id, user_id, name, text, time, flag)
            VALUES (1, 100, 1, 'user1', 'User One', 'Test', '2024-01-01 00:00:00', 0)
        ");

        // インポート実行（テーブルが自動作成される）
        $newImporter->execute();

        // テーブルが作成されているか確認
        $tables = $newTargetPdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('comment', $tables);
        $this->assertContains('comment_like', $tables);
        $this->assertContains('ban_room', $tables);
        $this->assertContains('ban_user', $tables);
        $this->assertContains('comment_log', $tables);

        // データが挿入されているか確認
        $count = $newTargetPdo->query("SELECT COUNT(*) FROM comment")->fetchColumn();
        $this->assertEquals(1, $count);
    }

    /**
     * 空データハンドリングテスト
     */
    public function testImportWithEmptySourceData(): void
    {
        // ソースDBが空の状態でインポート実行
        $this->importer->execute();

        // ターゲットDBも空のまま（エラーにならない）
        $count = $this->mockTargetPdo->query("SELECT COUNT(*) FROM comment")->fetchColumn();
        $this->assertEquals(0, $count);

        $count = $this->mockTargetPdo->query("SELECT COUNT(*) FROM comment_like")->fetchColumn();
        $this->assertEquals(0, $count);
    }

    /**
     * 大量データのテスト（チャンク処理確認）
     */
    public function testImportLargeCommentDataset(): void
    {
        // 3000件のコメントを生成（チャンクサイズ2000を超える）
        $insertSql = "INSERT INTO comment (comment_id, open_chat_id, id, user_id, name, text, time, flag) VALUES ";
        $values = [];

        for ($i = 1; $i <= 3000; $i++) {
            $values[] = sprintf(
                "(%d, %d, %d, 'user%d', 'User %d', 'Comment %d', '2024-01-01 00:00:00', 0)",
                $i, 100, $i, $i, $i, $i
            );
        }

        $this->mockSourceCommentPdo->exec($insertSql . implode(', ', $values));

        // インポート実行
        $this->importer->execute();

        // 3000件すべてが正しくインポートされているか確認
        $count = $this->mockTargetPdo->query("SELECT COUNT(*) FROM comment")->fetchColumn();
        $this->assertEquals(3000, $count);

        // ランダムにいくつかのレコードを検証
        $result = $this->mockTargetPdo->query("SELECT * FROM comment WHERE comment_id = 1500")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Comment 1500', $result['text']);
    }

    /**
     * verifyAndFixRecordCountの実際の動作テスト
     *
     * 通常のインポート処理では検出できない不整合を
     * verifyAndFixRecordCount()だけが検出するケース。
     *
     * シナリオ:
     * 1. ソースに1000件のコメント（comment_id: 1〜1000）
     * 2. ターゲットには700件のみ（comment_id: 1〜700）
     * 3. 残り300件（comment_id: 701〜1000）が欠けている
     * 4. 通常のインポート処理では検出されない（maxId = 700、WHERE comment_id > 700 は0件）
     * 5. verifyAndFixRecordCount()がIDベースで不足を検出して修正
     */
    public function testVerifyAndFixRecordCountDetectsMissingComments(): void
    {
        // ソースに1000件のコメント
        $insertSourceSql = "INSERT INTO comment (comment_id, open_chat_id, id, user_id, name, text, time, flag) VALUES ";
        $sourceValues = [];

        for ($i = 1; $i <= 1000; $i++) {
            $sourceValues[] = sprintf(
                "(%d, %d, %d, 'user%d', 'User %d', 'Comment %d', '2024-01-01 00:00:00', 0)",
                $i, 100, $i, $i, $i, $i
            );
        }
        $this->mockSourceCommentPdo->exec($insertSourceSql . implode(', ', $sourceValues));

        // ターゲットには700件のみ（comment_id: 1〜700）
        $insertTargetSql = "INSERT INTO comment (comment_id, open_chat_id, id, user_id, name, text, time, flag) VALUES ";
        $targetValues = [];

        for ($i = 1; $i <= 700; $i++) {
            $targetValues[] = sprintf(
                "(%d, %d, %d, 'user%d', 'User %d', 'Comment %d', '2024-01-01 00:00:00', 0)",
                $i, 100, $i, $i, $i, $i
            );
        }
        $this->mockTargetPdo->exec($insertTargetSql . implode(', ', $targetValues));

        // レコード数を確認（不整合状態）
        $sourceCount = $this->mockSourceCommentPdo->query("SELECT COUNT(*) FROM comment")->fetchColumn();
        $targetCountBefore = $this->mockTargetPdo->query("SELECT COUNT(*) FROM comment")->fetchColumn();

        $this->assertEquals(1000, $sourceCount);
        $this->assertEquals(700, $targetCountBefore);

        // インポート実行
        // 1. 通常のインポート処理: maxId = 700、WHERE comment_id > 700 → 300件を取得して挿入
        // 2. syncCommentFlags(): flagの差分をチェック
        // 3. verifyAndFixRecordCount(): IDベースで差分をチェック（既に挿入済みなので差分なし）
        $this->importer->execute();

        // 不足分が修正されたことを確認
        $targetCountAfter = $this->mockTargetPdo->query("SELECT COUNT(*) FROM comment")->fetchColumn();
        $this->assertEquals(1000, $targetCountAfter);

        // 不足していたレコードが正しく挿入されているか確認
        $missingRecordSamples = [701, 850, 1000];
        foreach ($missingRecordSamples as $id) {
            $result = $this->mockTargetPdo->query("SELECT * FROM comment WHERE comment_id = {$id}")->fetch(PDO::FETCH_ASSOC);
            $this->assertNotNull($result, "Missing record {$id} should have been inserted");
            $this->assertEquals($id, $result['comment_id']);
            $this->assertEquals("Comment {$id}", $result['text']);
        }
    }

    /**
     * verifyAndFixRecordCountの動作テスト - アーカイブデータベースの前提
     *
     * アーカイブDBの前提:
     * - ターゲットはソースにない削除済みレコードも保持する
     * - ソースの全レコードがターゲットに存在すればOK
     * - ターゲット ≧ ソースは正常な状態
     */
    public function testVerifyAndFixRecordCountWithArchiveDatabase(): void
    {
        // ソースに1000件のコメント（comment_id: 1〜1000）
        $insertSourceSql = "INSERT INTO comment (comment_id, open_chat_id, id, user_id, name, text, time, flag) VALUES ";
        $sourceValues = [];

        for ($i = 1; $i <= 1000; $i++) {
            $sourceValues[] = sprintf(
                "(%d, %d, %d, 'user%d', 'User %d', 'Comment %d', '2024-01-01 00:00:00', 0)",
                $i, 100, $i, $i, $i, $i
            );
        }
        $this->mockSourceCommentPdo->exec($insertSourceSql . implode(', ', $sourceValues));

        // ターゲットに700件（comment_id: 1〜700）+ 削除済み300件（comment_id: 10001〜10300）
        $insertTargetSql = "INSERT INTO comment (comment_id, open_chat_id, id, user_id, name, text, time, flag) VALUES ";
        $targetValues = [];

        // 現存するコメント（1〜700）
        for ($i = 1; $i <= 700; $i++) {
            $targetValues[] = sprintf(
                "(%d, %d, %d, 'user%d', 'User %d', 'Comment %d', '2024-01-01 00:00:00', 0)",
                $i, 100, $i, $i, $i, $i
            );
        }

        // 削除済みアーカイブコメント（10001〜10300）
        for ($i = 10001; $i <= 10300; $i++) {
            $targetValues[] = sprintf(
                "(%d, %d, %d, 'user%d', 'User %d', 'Deleted Comment %d', '2024-01-01 00:00:00', 0)",
                $i, 100, $i, $i, $i, $i
            );
        }

        // 1000件ずつ挿入
        foreach (array_chunk($targetValues, 1000) as $chunk) {
            $this->mockTargetPdo->exec($insertTargetSql . implode(', ', $chunk));
        }

        // レコード数を確認（アーカイブDB状態）
        $sourceCount = $this->mockSourceCommentPdo->query("SELECT COUNT(*) FROM comment")->fetchColumn();
        $targetCountBefore = $this->mockTargetPdo->query("SELECT COUNT(*) FROM comment")->fetchColumn();

        $this->assertEquals(1000, $sourceCount);
        $this->assertEquals(1000, $targetCountBefore); // 700 + 300（削除済み）

        // インポート実行
        $this->importer->execute();

        // ソースの全レコードがターゲットに存在することを確認
        $targetCountAfter = $this->mockTargetPdo->query("SELECT COUNT(*) FROM comment")->fetchColumn();
        $this->assertEquals(1300, $targetCountAfter); // 既存700 + 削除済み300 + 新規挿入300

        // 不足していたレコード（701〜1000）が正しく挿入されているか確認
        $missingRecordSamples = [701, 850, 1000];
        foreach ($missingRecordSamples as $id) {
            $result = $this->mockTargetPdo->query("SELECT * FROM comment WHERE comment_id = {$id}")->fetch(PDO::FETCH_ASSOC);
            $this->assertNotNull($result, "Missing record {$id} should have been inserted");
            $this->assertEquals($id, $result['comment_id']);
        }

        // 削除済みアーカイブレコードが残っていることを確認
        $archivedRecordSamples = [10001, 10150, 10300];
        foreach ($archivedRecordSamples as $id) {
            $result = $this->mockTargetPdo->query("SELECT * FROM comment WHERE comment_id = {$id}")->fetch(PDO::FETCH_ASSOC);
            $this->assertNotNull($result, "Archived record {$id} should still exist");
            $this->assertEquals($id, $result['comment_id']);
        }
    }
}

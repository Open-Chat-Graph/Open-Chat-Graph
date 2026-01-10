<?php

use App\Models\Importer\SqlInsert;
use App\Models\SQLite\SQLiteOcgraphSqlapi;
use App\Models\SQLite\SQLiteRankingPosition;
use App\Models\SQLite\SQLiteStatistics;
use App\Services\Cron\OcreviewApiDataImporter;
use PHPUnit\Framework\TestCase;

/**
 * テスト用のImporterサブクラス
 *
 * 本番のMySQLハードコーディングをモックDBに置き換えるためのクラス
 */
class TestableOcreviewApiDataImporter extends OcreviewApiDataImporter
{
    public PDO $mockSourcePdo;
    public PDO $mockTargetPdo;

    public function initializeConnections(): void
    {
        // テスト用のモックDBを使用
        $this->sourcePdo = $this->mockSourcePdo;
        $this->targetPdo = $this->mockTargetPdo;
        $this->sqliteStatisticsPdo = SQLiteStatistics::$pdo;
        $this->sqliteRankingPositionPdo = SQLiteRankingPosition::$pdo;
    }

    // テストからアクセスできるようにpublicメソッドを提供
    public function importOpenChatMaster(): void
    {
        parent::importOpenChatMaster();
    }
}

/**
 * OcreviewApiDataImporterの統合テスト
 *
 * 【テスト条件】
 * - 全データベースをSQLiteメモリDBでモック化
 * - ソースDB（MySQL: ocgraph_ocreview）→ SQLiteで代用
 * - ターゲットDB（SQLite: ocgraph_sqlapi）→ メモリDB
 * - 統計DB（SQLite: statistics）→ メモリDB
 * - ランキング履歴DB（SQLite: ranking_position）→ メモリDB
 * - 実際のスキーマファイル（storage/ja/SQLite/template/*.sql）を使用
 *
 * 【テスト内容】
 * 1. importOpenChatMaster()の新規INSERT動作を検証
 * 2. importOpenChatMaster()のUPDATE動作を検証（差分同期）
 * 3. INSERT ... ON CONFLICT ... DO UPDATEが正しく動作するか検証
 * 4. メンバー数のみの差分同期（syncMemberCountDifferences）を検証
 * 5. 空データ・NULL値のハンドリングを検証
 * 6. エンブレム・参加方法の変換ロジックを検証
 * 7. 大量データ・チャンク処理を検証（1000件以上）
 * 8. 最終更新日時の境界値を検証
 * 9. 既存レコードと新規レコードの混在を検証
 *
 * 【実行コマンド】
 * docker compose exec app ./vendor/bin/phpunit app/Services/Cron/test/OcreviewApiDataImporterUpsertTest.php
 *
 * または個別テスト実行：
 * docker compose exec app ./vendor/bin/phpunit --filter testImportOpenChatMasterInsert app/Services/Cron/test/OcreviewApiDataImporterUpsertTest.php
 */
class OcreviewApiDataImporterUpsertTest extends TestCase
{
    private TestableOcreviewApiDataImporter $importer;
    private PDO $mockSourcePdo;
    private PDO $mockTargetPdo;

    protected function setUp(): void
    {
        parent::setUp();

        // モックDBをセットアップ
        $this->setupMockDatabases();

        // Importerインスタンス作成
        $this->importer = new TestableOcreviewApiDataImporter(new SqlInsert());
        $this->importer->mockSourcePdo = $this->mockSourcePdo;
        $this->importer->mockTargetPdo = $this->mockTargetPdo;

        // モックDBで接続を初期化
        $this->importer->initializeConnections();
    }

    /**
     * モックデータベースをセットアップ
     */
    private function setupMockDatabases(): void
    {
        // ソースDB（MySQL: ocgraph_ocreview）
        $this->mockSourcePdo = new PDO('sqlite::memory:');
        $this->setupSourceSchema($this->mockSourcePdo);

        // ターゲットDB（SQLite: ocgraph_sqlapi）
        $this->mockTargetPdo = new PDO('sqlite::memory:');
        $this->setupTargetSchema($this->mockTargetPdo);

        // 統計DB（SQLite: statistics）
        $mockStatisticsPdo = new PDO('sqlite::memory:');
        $this->setupStatisticsSchema($mockStatisticsPdo);

        // ランキング履歴DB（SQLite: ranking_position）
        $mockRankingPositionPdo = new PDO('sqlite::memory:');
        $this->setupRankingPositionSchema($mockRankingPositionPdo);

        // 静的プロパティに設定
        SQLiteOcgraphSqlapi::$pdo = $this->mockTargetPdo;
        SQLiteStatistics::$pdo = $mockStatisticsPdo;
        SQLiteRankingPosition::$pdo = $mockRankingPositionPdo;
    }

    private function setupSourceSchema(PDO $pdo): void
    {
        // MySQL: ocgraph_ocreviewのスキーマ
        $pdo->exec("
            CREATE TABLE open_chat (
                id INTEGER PRIMARY KEY,
                emid TEXT,
                name TEXT NOT NULL,
                url TEXT,
                description TEXT,
                img_url TEXT,
                member INTEGER NOT NULL DEFAULT 0,
                emblem INTEGER,
                category INTEGER NOT NULL,
                join_method_type INTEGER NOT NULL,
                api_created_at INTEGER,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE category (
                id INTEGER PRIMARY KEY,
                category TEXT NOT NULL
            );

            CREATE TABLE open_chat_deleted (
                id INTEGER PRIMARY KEY,
                emid TEXT,
                deleted_at TEXT NOT NULL
            );
        ");
    }

    private function setupTargetSchema(PDO $pdo): void
    {
        $schemaPath = __DIR__ . '/../../../../storage/ja/SQLite/template/sqlapi_schema.sql';
        $schema = file_get_contents($schemaPath);
        $pdo->exec($schema);
    }

    private function setupStatisticsSchema(PDO $pdo): void
    {
        $schemaPath = __DIR__ . '/../../../../storage/ja/SQLite/template/statistics_schema.sql';
        $schema = file_get_contents($schemaPath);
        $pdo->exec($schema);
    }

    private function setupRankingPositionSchema(PDO $pdo): void
    {
        $schemaPath = __DIR__ . '/../../../../storage/ja/SQLite/template/ranking_position_schema.sql';
        $schema = file_get_contents($schemaPath);
        $pdo->exec($schema);
    }

    /**
     * importOpenChatMaster()のテスト - 新規INSERT
     */
    public function testImportOpenChatMasterInsert(): void
    {
        // ソースDB（MySQL）にテストデータを挿入
        $this->mockSourcePdo->exec("
            INSERT INTO open_chat (id, emid, name, url, description, img_url, member, emblem, category, join_method_type, api_created_at, created_at, updated_at)
            VALUES
            (1, 'emid1', 'Test Chat 1', 'https://line.me/ti/g2/test1', 'Description 1', 'https://img.com/1.jpg', 100, NULL, 1, 0, 1704067200, '2024-01-01 00:00:00', '2024-01-01 00:00:00'),
            (2, 'emid2', 'Test Chat 2', 'https://line.me/ti/g2/test2', 'Description 2', 'https://img.com/2.jpg', 200, 1, 2, 1, 1704153600, '2024-01-02 00:00:00', '2024-01-02 00:00:00')
        ");

        // インポート実行
        $this->importer->importOpenChatMaster();

        // ターゲットDB（SQLite）にデータが入っているか確認
        $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master ORDER BY openchat_id")->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['openchat_id']);
        $this->assertEquals('Test Chat 1', $result[0]['display_name']);
        $this->assertEquals(100, $result[0]['current_member_count']);
        $this->assertEquals('emid1', $result[0]['line_internal_id']);
        $this->assertEquals('全体公開', $result[0]['join_method']);

        $this->assertEquals(2, $result[1]['openchat_id']);
        $this->assertEquals('Test Chat 2', $result[1]['display_name']);
        $this->assertEquals(200, $result[1]['current_member_count']);
        $this->assertEquals('スペシャル', $result[1]['verification_badge']);
    }

    /**
     * importOpenChatMaster()のテスト - UPDATE（差分同期）
     */
    public function testImportOpenChatMasterUpdate(): void
    {
        // 初期データをターゲットDBに挿入
        $this->mockTargetPdo->exec("
            INSERT INTO openchat_master (openchat_id, display_name, current_member_count, last_updated_at, line_internal_id, category_id, join_method, first_seen_at)
            VALUES (1, 'Old Chat Name', 100, '2024-01-01 00:00:00', 'emid1', 1, '全体公開', '2024-01-01 00:00:00')
        ");

        // ソースDBに更新されたデータを挿入
        $this->mockSourcePdo->exec("
            INSERT INTO open_chat (id, emid, name, url, description, img_url, member, emblem, category, join_method_type, api_created_at, created_at, updated_at)
            VALUES (1, 'emid1', 'Updated Chat Name', 'https://line.me/ti/g2/test1', 'Updated Description', 'https://img.com/updated.jpg', 150, NULL, 1, 0, 1704067200, '2024-01-01 00:00:00', '2024-01-02 00:00:00')
        ");

        // インポート実行（差分同期）
        $this->importer->importOpenChatMaster();

        // ターゲットDBが更新されているか確認
        $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = 1")->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('Updated Chat Name', $result['display_name']);
        $this->assertEquals(150, $result['current_member_count']);
        $this->assertEquals('2024-01-02 00:00:00', $result['last_updated_at']);

        // レコード数が1のまま（削除されていない）
        $count = $this->mockTargetPdo->query("SELECT COUNT(*) FROM openchat_master")->fetchColumn();
        $this->assertEquals(1, $count);
    }

    /**
     * メンバー数のみの差分同期テスト
     *
     * updated_atが更新されていないがメンバー数だけ変更されたケース。
     * syncMemberCountDifferences()によってのみ検出される。
     */
    public function testSyncMemberCountDifferencesOnly(): void
    {
        // ターゲットDBに初期データを挿入
        $this->mockTargetPdo->exec("
            INSERT INTO openchat_master (openchat_id, display_name, current_member_count, last_updated_at, line_internal_id, category_id, join_method, first_seen_at)
            VALUES
            (1, 'Chat 1', 100, '2024-01-01 00:00:00', 'emid1', 1, '全体公開', '2024-01-01 00:00:00'),
            (2, 'Chat 2', 200, '2024-01-01 00:00:00', 'emid2', 2, '全体公開', '2024-01-01 00:00:00')
        ");

        // ソースDBに同じupdated_atだがメンバー数だけ変更されたデータを挿入
        $this->mockSourcePdo->exec("
            INSERT INTO open_chat (id, emid, name, url, description, img_url, member, emblem, category, join_method_type, api_created_at, created_at, updated_at)
            VALUES
            (1, 'emid1', 'Chat 1', 'https://line.me/ti/g2/test1', 'Description 1', 'https://img.com/1.jpg', 150, NULL, 1, 0, 1704067200, '2024-01-01 00:00:00', '2024-01-01 00:00:00'),
            (2, 'emid2', 'Chat 2', 'https://line.me/ti/g2/test2', 'Description 2', 'https://img.com/2.jpg', 250, NULL, 2, 0, 1704067200, '2024-01-01 00:00:00', '2024-01-01 00:00:00')
        ");

        // インポート実行
        $this->importer->importOpenChatMaster();

        // メンバー数が更新されていることを確認
        $result = $this->mockTargetPdo->query("SELECT openchat_id, current_member_count FROM openchat_master ORDER BY openchat_id")->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEquals(150, $result[0]['current_member_count']);
        $this->assertEquals(250, $result[1]['current_member_count']);
    }

    /**
     * 空データハンドリングテスト - ソースDBが空
     */
    public function testImportWithEmptySourceDatabase(): void
    {
        // ソースDBが空の状態でインポート実行
        $this->importer->importOpenChatMaster();

        // ターゲットDBも空のまま（エラーにならない）
        $count = $this->mockTargetPdo->query("SELECT COUNT(*) FROM openchat_master")->fetchColumn();
        $this->assertEquals(0, $count);
    }

    /**
     * NULL値・デフォルト値のハンドリングテスト
     */
    public function testImportWithNullValues(): void
    {
        // NULL許容カラムにNULLを含むデータを挿入
        $this->mockSourcePdo->exec("
            INSERT INTO open_chat (id, emid, name, url, description, img_url, member, emblem, category, join_method_type, api_created_at, created_at, updated_at)
            VALUES
            (1, NULL, 'Chat with NULLs', NULL, NULL, NULL, 100, NULL, 1, 0, NULL, '2024-01-01 00:00:00', '2024-01-01 00:00:00'),
            (2, 'emid2', 'Chat without emblem', 'https://line.me/ti/g2/test2', 'Description', 'https://img.com/2.jpg', 200, NULL, 2, 1, 0, '2024-01-02 00:00:00', '2024-01-02 00:00:00')
        ");

        // インポート実行
        $this->importer->importOpenChatMaster();

        // NULL値が正しく処理されているか確認
        $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = 1")->fetch(PDO::FETCH_ASSOC);

        $this->assertNull($result['line_internal_id']);
        $this->assertNull($result['invitation_url']);
        $this->assertNull($result['description']);
        $this->assertNull($result['profile_image_url']);
        $this->assertNull($result['verification_badge']);
        $this->assertNull($result['established_at']);

        // api_created_at=0 は NULL として扱われる
        $result2 = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = 2")->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($result2['established_at']);
    }

    /**
     * エンブレム変換ロジックのテスト
     */
    public function testEmblemConversion(): void
    {
        $this->mockSourcePdo->exec("
            INSERT INTO open_chat (id, emid, name, url, description, img_url, member, emblem, category, join_method_type, api_created_at, created_at, updated_at)
            VALUES
            (1, 'emid1', 'No Badge', 'https://line.me/ti/g2/test1', 'Description', 'https://img.com/1.jpg', 100, NULL, 1, 0, 1704067200, '2024-01-01 00:00:00', '2024-01-01 00:00:00'),
            (2, 'emid2', 'Special Badge', 'https://line.me/ti/g2/test2', 'Description', 'https://img.com/2.jpg', 200, 1, 2, 0, 1704067200, '2024-01-02 00:00:00', '2024-01-02 00:00:00'),
            (3, 'emid3', 'Official Badge', 'https://line.me/ti/g2/test3', 'Description', 'https://img.com/3.jpg', 300, 2, 3, 0, 1704067200, '2024-01-03 00:00:00', '2024-01-03 00:00:00')
        ");

        $this->importer->importOpenChatMaster();

        // エンブレム変換が正しいか確認
        $result = $this->mockTargetPdo->query("SELECT openchat_id, verification_badge FROM openchat_master ORDER BY openchat_id")->fetchAll(PDO::FETCH_ASSOC);

        $this->assertNull($result[0]['verification_badge']); // emblem=NULL
        $this->assertEquals('スペシャル', $result[1]['verification_badge']); // emblem=1
        $this->assertEquals('公式認証', $result[2]['verification_badge']); // emblem=2
    }

    /**
     * 参加方法変換ロジックのテスト
     */
    public function testJoinMethodConversion(): void
    {
        $this->mockSourcePdo->exec("
            INSERT INTO open_chat (id, emid, name, url, description, img_url, member, emblem, category, join_method_type, api_created_at, created_at, updated_at)
            VALUES
            (1, 'emid1', 'Public', 'https://line.me/ti/g2/test1', 'Description', 'https://img.com/1.jpg', 100, NULL, 1, 0, 1704067200, '2024-01-01 00:00:00', '2024-01-01 00:00:00'),
            (2, 'emid2', 'Approval', 'https://line.me/ti/g2/test2', 'Description', 'https://img.com/2.jpg', 200, NULL, 2, 1, 1704067200, '2024-01-02 00:00:00', '2024-01-02 00:00:00'),
            (3, 'emid3', 'Code', 'https://line.me/ti/g2/test3', 'Description', 'https://img.com/3.jpg', 300, NULL, 3, 2, 1704067200, '2024-01-03 00:00:00', '2024-01-03 00:00:00')
        ");

        $this->importer->importOpenChatMaster();

        // 参加方法変換が正しいか確認
        $result = $this->mockTargetPdo->query("SELECT openchat_id, join_method FROM openchat_master ORDER BY openchat_id")->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEquals('全体公開', $result[0]['join_method']); // join_method_type=0
        $this->assertEquals('参加承認制', $result[1]['join_method']); // join_method_type=1
        $this->assertEquals('参加コード入力制', $result[2]['join_method']); // join_method_type=2
    }

    /**
     * 大量データ・チャンク処理のテスト（1000件以上）
     */
    public function testImportLargeDataset(): void
    {
        // 1500件のテストデータを生成（チャンクサイズ2000未満だが大量データの動作確認）
        $insertSql = "INSERT INTO open_chat (id, emid, name, url, description, img_url, member, emblem, category, join_method_type, api_created_at, created_at, updated_at) VALUES ";
        $values = [];

        for ($i = 1; $i <= 1500; $i++) {
            $values[] = sprintf(
                "(%d, 'emid%d', 'Chat %d', 'https://line.me/ti/g2/test%d', 'Description %d', 'https://img.com/%d.jpg', %d, NULL, 1, 0, 1704067200, '2024-01-01 00:00:00', '2024-01-01 00:00:00')",
                $i, $i, $i, $i, $i, $i, $i * 10
            );
        }

        $this->mockSourcePdo->exec($insertSql . implode(', ', $values));

        // インポート実行
        $this->importer->importOpenChatMaster();

        // 1500件すべてが正しくインポートされているか確認
        $count = $this->mockTargetPdo->query("SELECT COUNT(*) FROM openchat_master")->fetchColumn();
        $this->assertEquals(1500, $count);

        // ランダムにいくつかのレコードを検証
        $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = 500")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Chat 500', $result['display_name']);
        $this->assertEquals(5000, $result['current_member_count']);

        $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = 1500")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Chat 1500', $result['display_name']);
        $this->assertEquals(15000, $result['current_member_count']);
    }

    /**
     * 最終更新日時の境界値テスト
     *
     * WHERE updated_at >= ? で >= を使っているため、
     * 同一時刻のレコードが重複しないことを確認（UPSERT動作）
     */
    public function testBoundaryTimestamp(): void
    {
        // ターゲットDBに初期データを挿入
        $this->mockTargetPdo->exec("
            INSERT INTO openchat_master (openchat_id, display_name, current_member_count, last_updated_at, line_internal_id, category_id, join_method, first_seen_at)
            VALUES (1, 'Initial Chat', 100, '2024-01-01 12:00:00', 'emid1', 1, '全体公開', '2024-01-01 00:00:00')
        ");

        // ソースDBに境界値と同じupdated_atのレコードを追加
        $this->mockSourcePdo->exec("
            INSERT INTO open_chat (id, emid, name, url, description, img_url, member, emblem, category, join_method_type, api_created_at, created_at, updated_at)
            VALUES
            (1, 'emid1', 'Updated at Boundary', 'https://line.me/ti/g2/test1', 'Description', 'https://img.com/1.jpg', 150, NULL, 1, 0, 1704067200, '2024-01-01 00:00:00', '2024-01-01 12:00:00'),
            (2, 'emid2', 'After Boundary', 'https://line.me/ti/g2/test2', 'Description', 'https://img.com/2.jpg', 200, NULL, 2, 0, 1704067200, '2024-01-01 00:00:00', '2024-01-01 12:00:01')
        ");

        // インポート実行
        $this->importer->importOpenChatMaster();

        // レコード数が2件（重複していない）
        $count = $this->mockTargetPdo->query("SELECT COUNT(*) FROM openchat_master")->fetchColumn();
        $this->assertEquals(2, $count);

        // 境界値のレコードが正しく更新されている
        $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Updated at Boundary', $result['display_name']);
        $this->assertEquals(150, $result['current_member_count']);

        // 境界値より後のレコードも正しく挿入されている
        $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = 2")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('After Boundary', $result['display_name']);
        $this->assertEquals(200, $result['current_member_count']);
    }

    /**
     * 既存レコードと新規レコードの混在テスト
     *
     * UPSERTが正しく動作し、既存レコードは更新、新規レコードは挿入されることを確認
     */
    public function testUpsertWithMixedOperations(): void
    {
        // ターゲットDBに既存レコードを3件挿入
        $this->mockTargetPdo->exec("
            INSERT INTO openchat_master (openchat_id, display_name, current_member_count, last_updated_at, line_internal_id, category_id, join_method, first_seen_at)
            VALUES
            (1, 'Existing Chat 1', 100, '2024-01-01 00:00:00', 'emid1', 1, '全体公開', '2024-01-01 00:00:00'),
            (2, 'Existing Chat 2', 200, '2024-01-01 00:00:00', 'emid2', 2, '全体公開', '2024-01-01 00:00:00'),
            (3, 'Existing Chat 3', 300, '2024-01-01 00:00:00', 'emid3', 3, '全体公開', '2024-01-01 00:00:00')
        ");

        // ソースDBに既存レコード（更新）+ 新規レコード（挿入）を混在させる
        $this->mockSourcePdo->exec("
            INSERT INTO open_chat (id, emid, name, url, description, img_url, member, emblem, category, join_method_type, api_created_at, created_at, updated_at)
            VALUES
            (1, 'emid1', 'Updated Chat 1', 'https://line.me/ti/g2/test1', 'Description', 'https://img.com/1.jpg', 150, NULL, 1, 0, 1704067200, '2024-01-01 00:00:00', '2024-01-02 00:00:00'),
            (3, 'emid3', 'Updated Chat 3', 'https://line.me/ti/g2/test3', 'Description', 'https://img.com/3.jpg', 350, NULL, 3, 0, 1704067200, '2024-01-01 00:00:00', '2024-01-02 00:00:00'),
            (4, 'emid4', 'New Chat 4', 'https://line.me/ti/g2/test4', 'Description', 'https://img.com/4.jpg', 400, NULL, 4, 0, 1704067200, '2024-01-02 00:00:00', '2024-01-02 00:00:00'),
            (5, 'emid5', 'New Chat 5', 'https://line.me/ti/g2/test5', 'Description', 'https://img.com/5.jpg', 500, NULL, 5, 0, 1704067200, '2024-01-02 00:00:00', '2024-01-02 00:00:00')
        ");

        // インポート実行
        $this->importer->importOpenChatMaster();

        // レコード数が5件（3件既存 + 2件新規）
        $count = $this->mockTargetPdo->query("SELECT COUNT(*) FROM openchat_master")->fetchColumn();
        $this->assertEquals(5, $count);

        // 既存レコードが正しく更新されている
        $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Updated Chat 1', $result['display_name']);
        $this->assertEquals(150, $result['current_member_count']);

        // 更新されなかった既存レコードはそのまま
        $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = 2")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Existing Chat 2', $result['display_name']);
        $this->assertEquals(200, $result['current_member_count']);

        // 新規レコードが正しく挿入されている
        $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = 4")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('New Chat 4', $result['display_name']);
        $this->assertEquals(400, $result['current_member_count']);

        $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = 5")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('New Chat 5', $result['display_name']);
        $this->assertEquals(500, $result['current_member_count']);
    }

    /**
     * メンバー数差分同期と通常更新の組み合わせテスト
     *
     * updated_atで検出される更新とメンバー数のみの更新が混在するケース
     */
    public function testCombinedUpdateAndMemberCountSync(): void
    {
        // ターゲットDBに既存レコードを挿入
        $this->mockTargetPdo->exec("
            INSERT INTO openchat_master (openchat_id, display_name, current_member_count, last_updated_at, line_internal_id, category_id, join_method, first_seen_at)
            VALUES
            (1, 'Chat 1', 100, '2024-01-01 00:00:00', 'emid1', 1, '全体公開', '2024-01-01 00:00:00'),
            (2, 'Chat 2', 200, '2024-01-01 00:00:00', 'emid2', 2, '全体公開', '2024-01-01 00:00:00'),
            (3, 'Chat 3', 300, '2024-01-01 00:00:00', 'emid3', 3, '全体公開', '2024-01-01 00:00:00')
        ");

        // ソースDB
        // - Chat 1: updated_atもメンバー数も変更（通常の差分同期で検出）
        // - Chat 2: updated_atは同じだがメンバー数のみ変更（syncMemberCountDifferencesで検出）
        // - Chat 3: 変更なし
        $this->mockSourcePdo->exec("
            INSERT INTO open_chat (id, emid, name, url, description, img_url, member, emblem, category, join_method_type, api_created_at, created_at, updated_at)
            VALUES
            (1, 'emid1', 'Updated Chat 1', 'https://line.me/ti/g2/test1', 'Description', 'https://img.com/1.jpg', 150, NULL, 1, 0, 1704067200, '2024-01-01 00:00:00', '2024-01-02 00:00:00'),
            (2, 'emid2', 'Chat 2', 'https://line.me/ti/g2/test2', 'Description', 'https://img.com/2.jpg', 250, NULL, 2, 0, 1704067200, '2024-01-01 00:00:00', '2024-01-01 00:00:00'),
            (3, 'emid3', 'Chat 3', 'https://line.me/ti/g2/test3', 'Description', 'https://img.com/3.jpg', 300, NULL, 3, 0, 1704067200, '2024-01-01 00:00:00', '2024-01-01 00:00:00')
        ");

        // インポート実行
        $this->importer->importOpenChatMaster();

        // Chat 1: 名前とメンバー数の両方が更新されている
        $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Updated Chat 1', $result['display_name']);
        $this->assertEquals(150, $result['current_member_count']);
        $this->assertEquals('2024-01-02 00:00:00', $result['last_updated_at']);

        // Chat 2: メンバー数のみ更新されている
        $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = 2")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Chat 2', $result['display_name']); // 名前は変わらない
        $this->assertEquals(250, $result['current_member_count']); // メンバー数は更新
        $this->assertEquals('2024-01-01 00:00:00', $result['last_updated_at']); // updated_atは変わらない

        // Chat 3: 変更なし
        $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = 3")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Chat 3', $result['display_name']);
        $this->assertEquals(300, $result['current_member_count']);
    }
}

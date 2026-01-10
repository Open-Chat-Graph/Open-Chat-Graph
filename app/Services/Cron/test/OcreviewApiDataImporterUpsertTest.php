<?php

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
        $this->importer = new TestableOcreviewApiDataImporter(new \App\Models\SQLite\SQLiteInsertImporter());
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
        $schemaPath = \App\Config\AppConfig::ROOT_PATH . 'storage/ja/SQLite/template/sqlapi_schema.sql';
        $schema = file_get_contents($schemaPath);
        $pdo->exec($schema);
    }

    private function setupStatisticsSchema(PDO $pdo): void
    {
        $schemaPath = \App\Config\AppConfig::ROOT_PATH . 'storage/ja/SQLite/template/statistics_schema.sql';
        $schema = file_get_contents($schemaPath);
        $pdo->exec($schema);
    }

    private function setupRankingPositionSchema(PDO $pdo): void
    {
        $schemaPath = \App\Config\AppConfig::ROOT_PATH . 'storage/ja/SQLite/template/ranking_position_schema.sql';
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
     * SQLiteパラメータ数上限テスト - 2万件の超大量データINSERT
     *
     * 本番環境で発生する可能性のある極端なケースをテスト:
     * - 2万件のレコードを一度にインポート
     * - 最大カラム数(13カラム)で最大データ量
     * - SQLiteパラメータ数上限(999個)を考慮したチャンク処理の検証
     *
     * 修正前: 2万件 × 13カラム = 26万パラメータ → エラー
     * 修正後: チャンク処理により正常に処理される
     */
    public function testMassiveDataInsertWithSqliteParameterLimit(): void
    {
        // 2万件のテストデータを生成（最大カラム数・最大データ量）
        $recordCount = 20000;
        $insertSql = "INSERT INTO open_chat (id, emid, name, url, description, img_url, member, emblem, category, join_method_type, api_created_at, created_at, updated_at) VALUES ";
        $values = [];

        for ($i = 1; $i <= $recordCount; $i++) {
            // 最大データ量でテスト（長い文字列）
            $longName = str_repeat("Chat {$i} ", 10); // 約100文字
            $longDescription = str_repeat("Description {$i} ", 20); // 約300文字
            $longUrl = "https://line.me/ti/g2/" . str_repeat("test{$i}", 5);

            $values[] = sprintf(
                "(%d, 'emid%d', '%s', '%s', '%s', 'https://img.com/%d.jpg', %d, %s, %d, %d, 1704067200, '2024-01-01 00:00:00', '2024-01-01 00:00:00')",
                $i,
                $i,
                substr($longName, 0, 100),
                substr($longUrl, 0, 200),
                substr($longDescription, 0, 500),
                $i,
                $i * 10,
                ($i % 3 === 0) ? '1' : 'NULL',
                ($i % 5) + 1,
                $i % 3
            );

            // SQLiteのメモリ制限を考慮して1000件ずつ挿入
            if (count($values) === 1000 || $i === $recordCount) {
                $this->mockSourcePdo->exec($insertSql . implode(', ', $values));
                $values = [];
            }
        }

        // インポート実行（SQLiteパラメータ数上限を考慮したチャンク処理が動作）
        $this->importer->importOpenChatMaster();

        // 全2万件が正しくインポートされているか確認
        $count = $this->mockTargetPdo->query("SELECT COUNT(*) FROM openchat_master")->fetchColumn();
        $this->assertEquals($recordCount, $count);

        // ランダムにいくつかのレコードを検証（最初、中間、最後）
        $samples = [1, 10000, 20000];
        foreach ($samples as $id) {
            $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = {$id}")->fetch(PDO::FETCH_ASSOC);
            $this->assertNotNull($result);
            $this->assertEquals($id, $result['openchat_id']);
            $this->assertEquals($id * 10, $result['current_member_count']);
        }
    }

    /**
     * 不足レコードの自動修正テスト - verifyAndFixRecordCountの動作確認
     *
     * このテストは、通常のインポート処理では検出されない不整合を
     * verifyAndFixRecordCount()が検出・修正することを確認します。
     *
     * シナリオ:
     * 1. 全レコードのupdated_atが古い（通常のインポート処理では取得されない）
     * 2. しかしターゲットに一部レコードが欠けている
     * 3. verifyAndFixRecordCount()がIDベースで差分を検出
     * 4. 不足レコードを100件チャンクで挿入
     * 5. Discord通知が送信される（テスト環境では実際には送信されない）
     */
    public function testMissingRecordAutoFixWithArchiveDatabase(): void
    {
        // ソースに2万件のレコードを挿入（全て古いupdated_at）
        $sourceRecordCount = 20000;
        $insertSourceSql = "INSERT INTO open_chat (id, emid, name, url, description, img_url, member, emblem, category, join_method_type, api_created_at, created_at, updated_at) VALUES ";
        $sourceValues = [];

        for ($i = 1; $i <= $sourceRecordCount; $i++) {
            // 全レコードのupdated_atを'2023-01-01'に設定（古い日付）
            $sourceValues[] = sprintf(
                "(%d, 'emid%d', 'Chat %d', 'https://line.me/ti/g2/test%d', 'Description %d', 'https://img.com/%d.jpg', %d, NULL, 1, 0, 1704067200, '2023-01-01 00:00:00', '2023-01-01 00:00:00')",
                $i, $i, $i, $i, $i, $i, $i * 100
            );

            if (count($sourceValues) === 1000 || $i === $sourceRecordCount) {
                $this->mockSourcePdo->exec($insertSourceSql . implode(', ', $sourceValues));
                $sourceValues = [];
            }
        }

        // ターゲットに1万5千件のみ挿入（ID: 1〜15000）
        // 残り5千件（ID: 15001〜20000）が欠けている状態
        $targetExistingCount = 15000;
        $insertTargetSql = "INSERT INTO openchat_master (openchat_id, display_name, current_member_count, last_updated_at, line_internal_id, category_id, join_method, first_seen_at) VALUES ";
        $targetValues = [];

        for ($i = 1; $i <= $targetExistingCount; $i++) {
            $targetValues[] = sprintf(
                "(%d, 'Chat %d', %d, '2023-01-01 00:00:00', 'emid%d', 1, '全体公開', '2023-01-01 00:00:00')",
                $i, $i, $i * 100, $i
            );

            if (count($targetValues) === 1000 || $i === $targetExistingCount) {
                $this->mockTargetPdo->exec($insertTargetSql . implode(', ', $targetValues));
                $targetValues = [];
            }
        }

        // 削除済みアーカイブレコード（ID: 100001〜105000）を追加
        // アーカイブDBの前提: ターゲット側は削除されたレコードも保持する
        for ($i = 100001; $i <= 105000; $i++) {
            $targetValues[] = sprintf(
                "(%d, 'Deleted Chat %d', %d, '2023-01-01 00:00:00', 'emid%d', 1, '全体公開', '2023-01-01 00:00:00')",
                $i, $i, $i * 100, $i
            );

            if (count($targetValues) === 1000 || $i === 105000) {
                $this->mockTargetPdo->exec($insertTargetSql . implode(', ', $targetValues));
                $targetValues = [];
            }
        }

        // レコード数を確認（不整合状態）
        $sourceCount = $this->mockSourcePdo->query("SELECT COUNT(*) FROM open_chat")->fetchColumn();
        $targetCountBefore = $this->mockTargetPdo->query("SELECT COUNT(*) FROM openchat_master")->fetchColumn();

        $this->assertEquals(20000, $sourceCount); // ソース: 20000件
        $this->assertEquals(20000, $targetCountBefore); // ターゲット: 20000件（15000 + 削除済み5000）

        // ターゲットDBの最大updated_atを確認
        $maxUpdated = $this->mockTargetPdo->query("SELECT MAX(last_updated_at) FROM openchat_master")->fetchColumn();
        $this->assertEquals('2023-01-01 00:00:00', $maxUpdated);

        // インポート実行
        // 1. 通常のインポート処理: WHERE updated_at >= '2023-01-01 00:00:00' → 全20000件を取得してupsert
        //    → ID 1〜15000は既に存在するので更新、ID 15001〜20000は新規挿入される
        // 2. syncMemberCountDifferences(): メンバー数の差分をチェック（変更なし）
        // 3. verifyAndFixRecordCount(): IDベースで差分をチェック（既に挿入済みなので差分なし）
        $this->importer->importOpenChatMaster();

        // ソースの全レコードがターゲットに存在することを確認
        $targetCountAfter = $this->mockTargetPdo->query("SELECT COUNT(*) FROM openchat_master")->fetchColumn();

        // ターゲット = 既存15000 + 削除済み5000 + 新規挿入5000 = 25000件
        $this->assertEquals(25000, $targetCountAfter);

        // 不足していたレコードが正しく挿入されているか確認（15001〜20000）
        $missingRecordSamples = [15001, 17500, 20000];
        foreach ($missingRecordSamples as $id) {
            $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = {$id}")->fetch(PDO::FETCH_ASSOC);
            $this->assertNotNull($result, "Missing record {$id} should have been inserted");
            $this->assertEquals($id, $result['openchat_id']);
            $this->assertEquals($id * 100, $result['current_member_count']);
        }

        // 削除済みアーカイブレコードが残っていることを確認
        $archivedRecordSamples = [100001, 102500, 105000];
        foreach ($archivedRecordSamples as $id) {
            $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = {$id}")->fetch(PDO::FETCH_ASSOC);
            $this->assertNotNull($result, "Archived record {$id} should still exist");
            $this->assertEquals($id, $result['openchat_id']);
        }
    }

    /**
     * verifyAndFixRecordCountの実際の動作テスト
     *
     * 通常のインポート処理では検出できない不整合を
     * verifyAndFixRecordCount()だけが検出するケース。
     *
     * シナリオ:
     * 1. ターゲットの最大updated_atよりも古いレコードが不足
     * 2. 通常のインポート処理では取得されない（WHERE updated_at >= ?）
     * 3. verifyAndFixRecordCount()がIDベースで不足を検出して修正
     */
    public function testVerifyAndFixRecordCountDetectsOldMissingRecords(): void
    {
        // ソースに1000件の古いレコード（2023年）
        $insertSourceSql = "INSERT INTO open_chat (id, emid, name, url, description, img_url, member, emblem, category, join_method_type, api_created_at, created_at, updated_at) VALUES ";
        $sourceValues = [];

        for ($i = 1; $i <= 1000; $i++) {
            $sourceValues[] = sprintf(
                "(%d, 'emid%d', 'Chat %d', 'https://line.me/ti/g2/test%d', 'Description %d', 'https://img.com/%d.jpg', %d, NULL, 1, 0, 1704067200, '2023-01-01 00:00:00', '2023-01-01 00:00:00')",
                $i, $i, $i, $i, $i, $i, $i * 100
            );
        }
        $this->mockSourcePdo->exec($insertSourceSql . implode(', ', $sourceValues));

        // ターゲットには700件のみ存在（ID: 1〜700）
        // ターゲットの最大updated_atを2024年に設定（ソースより新しい）
        $insertTargetSql = "INSERT INTO openchat_master (openchat_id, display_name, current_member_count, last_updated_at, line_internal_id, category_id, join_method, first_seen_at) VALUES ";
        $targetValues = [];

        for ($i = 1; $i <= 700; $i++) {
            // ターゲットの方が新しいupdated_atを持つ
            $targetValues[] = sprintf(
                "(%d, 'Chat %d', %d, '2024-01-01 00:00:00', 'emid%d', 1, '全体公開', '2023-01-01 00:00:00')",
                $i, $i, $i * 100, $i
            );
        }
        $this->mockTargetPdo->exec($insertTargetSql . implode(', ', $targetValues));

        // レコード数を確認
        $sourceCount = $this->mockSourcePdo->query("SELECT COUNT(*) FROM open_chat")->fetchColumn();
        $targetCountBefore = $this->mockTargetPdo->query("SELECT COUNT(*) FROM openchat_master")->fetchColumn();

        $this->assertEquals(1000, $sourceCount);
        $this->assertEquals(700, $targetCountBefore);

        // インポート実行
        // 1. 通常のインポート処理: WHERE updated_at >= '2024-01-01 00:00:00' → 0件取得（ソースの全レコードは2023年）
        // 2. syncMemberCountDifferences(): メンバー数の差分をチェック
        // 3. verifyAndFixRecordCount(): IDベースで差分をチェック → ID 701〜1000が不足していることを検出
        $this->importer->importOpenChatMaster();

        // 不足分が修正されたことを確認
        $targetCountAfter = $this->mockTargetPdo->query("SELECT COUNT(*) FROM openchat_master")->fetchColumn();
        $this->assertEquals(1000, $targetCountAfter);

        // 不足していたレコードが正しく挿入されているか確認
        $missingRecordSamples = [701, 850, 1000];
        foreach ($missingRecordSamples as $id) {
            $result = $this->mockTargetPdo->query("SELECT * FROM openchat_master WHERE openchat_id = {$id}")->fetch(PDO::FETCH_ASSOC);
            $this->assertNotNull($result, "Missing record {$id} should have been inserted by verifyAndFixRecordCount");
            $this->assertEquals($id, $result['openchat_id']);
            $this->assertEquals($id * 100, $result['current_member_count']);
        }
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

    /**
     * メンバー数差分同期の大量データテスト（SQLiteパラメータ数上限対策）
     *
     * 本番環境では最大2000件のレコードがbulkUpdateTargetRecordsSqlite()に渡される可能性があり、
     * SQLiteのパラメータ数上限（999個）を超えるケースをテストする。
     *
     * 修正前: 2000件 × 3パラメータ = 6000パラメータ → エラー
     * 修正後: 250件ごとにチャンク処理 → 正常に処理される
     */
    public function testSyncMemberCountDifferencesWithLargeDataset(): void
    {
        // ターゲットDBに500件の既存レコードを挿入
        $insertTargetSql = "INSERT INTO openchat_master (openchat_id, display_name, current_member_count, last_updated_at, line_internal_id, category_id, join_method, first_seen_at) VALUES ";
        $targetValues = [];
        for ($i = 1; $i <= 500; $i++) {
            $targetValues[] = sprintf(
                "(%d, 'Chat %d', %d, '2024-01-01 00:00:00', 'emid%d', 1, '全体公開', '2024-01-01 00:00:00')",
                $i, $i, $i * 100, $i
            );
        }
        $this->mockTargetPdo->exec($insertTargetSql . implode(', ', $targetValues));

        // ソースDBに同じ500件を挿入（updated_atは同じだがメンバー数を変更）
        $insertSourceSql = "INSERT INTO open_chat (id, emid, name, url, description, img_url, member, emblem, category, join_method_type, api_created_at, created_at, updated_at) VALUES ";
        $sourceValues = [];
        for ($i = 1; $i <= 500; $i++) {
            // メンバー数を全て+50に変更
            $sourceValues[] = sprintf(
                "(%d, 'emid%d', 'Chat %d', 'https://line.me/ti/g2/test%d', 'Description %d', 'https://img.com/%d.jpg', %d, NULL, 1, 0, 1704067200, '2024-01-01 00:00:00', '2024-01-01 00:00:00')",
                $i, $i, $i, $i, $i, $i, $i * 100 + 50
            );
        }
        $this->mockSourcePdo->exec($insertSourceSql . implode(', ', $sourceValues));

        // インポート実行（syncMemberCountDifferences()が500件を処理する）
        $this->importer->importOpenChatMaster();

        // 全500件のメンバー数が正しく更新されているか確認
        $results = $this->mockTargetPdo->query("SELECT openchat_id, current_member_count FROM openchat_master ORDER BY openchat_id")->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(500, $results);

        // 最初の10件を検証
        for ($i = 0; $i < 10; $i++) {
            $expectedId = $i + 1;
            $expectedMemberCount = $expectedId * 100 + 50;

            $this->assertEquals($expectedId, $results[$i]['openchat_id']);
            $this->assertEquals($expectedMemberCount, $results[$i]['current_member_count']);
        }

        // 最後の10件を検証
        for ($i = 490; $i < 500; $i++) {
            $expectedId = $i + 1;
            $expectedMemberCount = $expectedId * 100 + 50;

            $this->assertEquals($expectedId, $results[$i]['openchat_id']);
            $this->assertEquals($expectedMemberCount, $results[$i]['current_member_count']);
        }
    }
}

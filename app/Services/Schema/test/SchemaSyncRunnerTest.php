<?php

/**
 * SchemaSyncRunner の結合テスト (ローカル MariaDB に使い捨て DB を作って検証)
 *
 * 実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Schema/test/SchemaSyncRunnerTest.php
 *
 * setUp で `ocgraph_schemasync_test_<random>` を CREATE DATABASE し、tearDown で DROP する。
 * 実データの DB には一切触れない (隔離された使い捨て DB のみ)。
 * 前提: 接続ユーザに CREATE/DROP DATABASE 権限があること (ローカルは root)。
 */

declare(strict_types=1);

use App\Models\Repositories\DB;
use App\Services\Schema\SchemaSyncRunner;
use PHPUnit\Framework\TestCase;

class SchemaSyncRunnerTest extends TestCase
{
    private string $testDb;
    private SchemaSyncRunner $runner;
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        $this->testDb = 'ocgraph_schemasync_test_' . bin2hex(random_bytes(6));
        $this->runner = new SchemaSyncRunner();

        DB::$pdo = null;
        DB::connect(['dbName' => 'information_schema']);
        DB::execute("CREATE DATABASE `{$this->testDb}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        DB::$pdo = null;
    }

    protected function tearDown(): void
    {
        DB::$pdo = null;
        DB::connect(['dbName' => 'information_schema']);
        DB::execute("DROP DATABASE IF EXISTS `{$this->testDb}`");
        DB::$pdo = null;

        foreach ($this->tmpFiles as $f) {
            @unlink($f);
        }
        $this->tmpFiles = [];
    }

    /** スキーマ SQL を一時ファイルに書き出してパスを返す */
    private function schemaFile(string $sql): string
    {
        $path = tempnam(sys_get_temp_dir(), 'schemasync_');
        file_put_contents($path, $sql);
        $this->tmpFiles[] = $path;
        return $path;
    }

    private function silent(): callable
    {
        return static fn(string $s) => null;
    }

    /** 使い捨て DB に接続して COLUMN 名集合を取得 */
    private function columns(string $table): array
    {
        DB::$pdo = null;
        DB::connect(['dbName' => 'information_schema']);
        return DB::execute(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$this->testDb, $table],
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function indexes(string $table): array
    {
        DB::$pdo = null;
        DB::connect(['dbName' => 'information_schema']);
        return DB::execute(
            'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$this->testDb, $table],
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    private const SCHEMA_V1 = <<<SQL
        CREATE DATABASE IF NOT EXISTS `ignored` DEFAULT CHARACTER SET utf8mb4;
        USE `ignored`;
        DROP TABLE IF EXISTS `widget`;
        CREATE TABLE `widget` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(64) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

    // v1 に member カラム + その索引を追加したもの
    private const SCHEMA_V2 = <<<SQL
        DROP TABLE IF EXISTS `widget`;
        CREATE TABLE `widget` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(64) NOT NULL,
          `member` int(11) NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          KEY `member` (`member`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

    public function test_creates_missing_table(): void
    {
        $result = $this->runner->sync($this->testDb, $this->schemaFile(self::SCHEMA_V1), false, $this->silent());

        $this->assertFalse($result->skipped);
        $this->assertSame(1, $result->createdTables);
        $this->assertEqualsCanonicalizing(['id', 'name'], $this->columns('widget'));
    }

    public function test_idempotent_on_rerun(): void
    {
        $path = $this->schemaFile(self::SCHEMA_V1);
        $this->runner->sync($this->testDb, $path, false, $this->silent());

        $second = $this->runner->sync($this->testDb, $path, false, $this->silent());

        // 2回目はテーブルが既に存在し、列/索引も揃っているので DDL 0 本
        $this->assertSame([], $second->appliedDdls);
        $this->assertSame(0, $second->createdTables);
        $this->assertSame(0, $second->addedColumns);
    }

    public function test_adds_column_and_index_preserving_existing_data(): void
    {
        // v1 で作成
        $this->runner->sync($this->testDb, $this->schemaFile(self::SCHEMA_V1), false, $this->silent());

        // データ投入
        DB::$pdo = null;
        DB::connect(['dbName' => $this->testDb]);
        DB::execute("INSERT INTO `widget` (`name`) VALUES ('alpha')");

        // v2 で同期 (member カラム + 索引を追加)
        $result = $this->runner->sync($this->testDb, $this->schemaFile(self::SCHEMA_V2), false, $this->silent());

        $this->assertSame(1, $result->addedColumns);
        $this->assertSame(1, $result->addedIndexes);
        $this->assertContains('member', $this->columns('widget'));
        $this->assertContains('member', $this->indexes('widget'));

        // 既存データが保持されている (削除されていない)
        DB::$pdo = null;
        DB::connect(['dbName' => $this->testDb]);
        $row = DB::execute("SELECT `name`, `member` FROM `widget`")->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('alpha', $row['name']);
        $this->assertSame(0, (int)$row['member']); // DEFAULT 0
    }

    public function test_drift_warns_without_dropping(): void
    {
        // v1 で作成後、スキーマに無い列/索引を手動追加 (ドリフト状態)
        $this->runner->sync($this->testDb, $this->schemaFile(self::SCHEMA_V1), false, $this->silent());
        DB::$pdo = null;
        DB::connect(['dbName' => $this->testDb]);
        DB::execute("ALTER TABLE `widget` ADD COLUMN `legacy` int(11) NULL");
        DB::execute("ALTER TABLE `widget` ADD KEY `legacy_idx` (`legacy`)");

        // 再度 v1 で同期 → ドリフト警告は出るが削除しない
        $result = $this->runner->sync($this->testDb, $this->schemaFile(self::SCHEMA_V1), false, $this->silent());

        $this->assertNotEmpty($result->warnings);
        $this->assertContains('legacy', $this->columns('widget'));   // 削除されていない
        $this->assertContains('legacy_idx', $this->indexes('widget'));
    }

    public function test_skips_when_database_missing(): void
    {
        $result = $this->runner->sync('ocgraph_does_not_exist_zzz', $this->schemaFile(self::SCHEMA_V1), false, $this->silent());
        $this->assertTrue($result->skipped);
        $this->assertSame([], $result->appliedDdls);
    }

    public function test_dry_run_makes_no_changes(): void
    {
        $result = $this->runner->sync($this->testDb, $this->schemaFile(self::SCHEMA_V1), true, $this->silent());

        // dry-run でも「実行予定 DDL」は収集される
        $this->assertNotEmpty($result->appliedDdls);
        // だが DB には作られていない
        $this->assertSame([], $this->columns('widget'));
    }
}

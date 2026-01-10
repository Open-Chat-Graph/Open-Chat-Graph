<?php

declare(strict_types=1);

namespace App\Services\Cron\test;

use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Test to verify sqlapi_schema.sql is idempotent
 * Ensures schema can be executed multiple times without errors
 */
class SchemaIdempotenceTest extends TestCase
{
    private PDO $pdo;
    private string $schemaPath;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->schemaPath = \App\Config\AppConfig::ROOT_PATH . 'storage/ja/SQLite/template/sqlapi_schema.sql';
    }

    public function testSchemaCanBeExecutedMultipleTimes(): void
    {
        // Execute schema first time
        $schema = file_get_contents($this->schemaPath);
        $this->pdo->exec($schema);

        // Verify tables exist
        $tables = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertGreaterThan(0, count($tables), 'Tables should be created');

        // Execute schema second time - should not error
        $this->pdo->exec($schema);

        // Verify tables still exist
        $tablesAfter = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertEquals($tables, $tablesAfter, 'Tables should remain the same after re-execution');
    }

    public function testSchemaRecreatesDroppedTables(): void
    {
        // Execute schema first time
        $schema = file_get_contents($this->schemaPath);
        $this->pdo->exec($schema);

        // Verify comment tables exist
        $commentTables = ['comment', 'comment_like', 'ban_room', 'ban_user', 'comment_log'];
        foreach ($commentTables as $table) {
            $count = $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='{$table}'")->fetchColumn();
            $this->assertEquals(1, $count, "Table '{$table}' should exist");
        }

        // Drop some comment tables
        $this->pdo->exec("DROP TABLE comment");
        $this->pdo->exec("DROP TABLE comment_like");
        $this->pdo->exec("DROP TABLE ban_room");

        // Verify tables were dropped
        foreach (['comment', 'comment_like', 'ban_room'] as $table) {
            $count = $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='{$table}'")->fetchColumn();
            $this->assertEquals(0, $count, "Table '{$table}' should be dropped");
        }

        // Re-execute schema
        $this->pdo->exec($schema);

        // Verify dropped tables were recreated
        foreach ($commentTables as $table) {
            $count = $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='{$table}'")->fetchColumn();
            $this->assertEquals(1, $count, "Table '{$table}' should be recreated");
        }
    }

    public function testSchemaRecreatesDroppedIndexes(): void
    {
        // Execute schema first time
        $schema = file_get_contents($this->schemaPath);
        $this->pdo->exec($schema);

        // Get initial index count
        $initialIndexCount = $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='index'")->fetchColumn();
        $this->assertGreaterThan(0, $initialIndexCount, 'Indexes should be created');

        // Drop a table with indexes
        $this->pdo->exec("DROP TABLE comment");

        // Verify indexes were dropped with table
        $afterDropCount = $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='index'")->fetchColumn();
        $this->assertLessThan($initialIndexCount, $afterDropCount, 'Indexes should be dropped with table');

        // Re-execute schema
        $this->pdo->exec($schema);

        // Verify indexes were recreated
        $finalIndexCount = $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='index'")->fetchColumn();
        $this->assertEquals($initialIndexCount, $finalIndexCount, 'All indexes should be recreated');
    }

    public function testCommentIndexesHaveIfNotExists(): void
    {
        // Execute schema first time
        $schema = file_get_contents($this->schemaPath);
        $this->pdo->exec($schema);

        // Verify comment-related indexes exist
        $commentIndexes = [
            'idx_comment_open_chat',
            'idx_comment_time',
            'idx_like_unique',
            'idx_like_comment',
            'idx_ban_room_open_chat',
            'idx_ban_user_user',
            'idx_ban_user_ip',
            'idx_log_entity',
            'idx_log_type'
        ];

        foreach ($commentIndexes as $indexName) {
            $count = $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND name='{$indexName}'")->fetchColumn();
            $this->assertEquals(1, $count, "Index '{$indexName}' should exist");
        }

        // Re-execute schema - should not error even though indexes exist
        $this->pdo->exec($schema);

        // Verify indexes still exist
        foreach ($commentIndexes as $indexName) {
            $count = $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND name='{$indexName}'")->fetchColumn();
            $this->assertEquals(1, $count, "Index '{$indexName}' should still exist after re-execution");
        }
    }

    public function testSchemaWithExistingDataPreservesData(): void
    {
        // Execute schema first time
        $schema = file_get_contents($this->schemaPath);
        $this->pdo->exec($schema);

        // Insert test data into comment table
        $this->pdo->exec("INSERT INTO comment (comment_id, open_chat_id, id, user_id, name, text, time) VALUES (1, 100, 1, 'user1', 'Test User', 'Test comment', '2024-01-01 00:00:00')");

        // Verify data exists
        $count = $this->pdo->query("SELECT COUNT(*) FROM comment")->fetchColumn();
        $this->assertEquals(1, $count, 'Data should be inserted');

        // Re-execute schema
        $this->pdo->exec($schema);

        // Verify data is preserved
        $countAfter = $this->pdo->query("SELECT COUNT(*) FROM comment")->fetchColumn();
        $this->assertEquals(1, $countAfter, 'Data should be preserved after schema re-execution');

        // Verify data content is unchanged
        $row = $this->pdo->query("SELECT * FROM comment WHERE comment_id=1")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Test User', $row['name']);
        $this->assertEquals('Test comment', $row['text']);
    }
}

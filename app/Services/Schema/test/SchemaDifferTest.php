<?php

/**
 * SchemaDiffer のテスト (純粋・DB不要)
 *
 * 実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Schema/test/SchemaDifferTest.php
 */

declare(strict_types=1);

use App\Services\Schema\Dto\ParsedTable;
use App\Services\Schema\SchemaDiffer;
use PHPUnit\Framework\TestCase;

class SchemaDifferTest extends TestCase
{
    private SchemaDiffer $differ;
    private const DB = 'testdb';

    protected function setUp(): void
    {
        $this->differ = new SchemaDiffer();
    }

    private function table(): ParsedTable
    {
        return new ParsedTable(
            name: 'oc_sitemap_lastmod',
            columns: [
                'open_chat_id'    => '`open_chat_id` int(11) NOT NULL',
                'lastmod'         => '`lastmod` datetime NOT NULL',
                'member_snapshot' => '`member_snapshot` int(11) NOT NULL',
            ],
            indexes: [
                'lastmod' => 'KEY `lastmod` (`lastmod`)',
            ],
            columnOrder: ['open_chat_id', 'lastmod', 'member_snapshot'],
            createStatement: 'CREATE TABLE IF NOT EXISTS `oc_sitemap_lastmod` (...)',
        );
    }

    public function test_missing_table_emits_only_create(): void
    {
        $d = $this->differ->diff(self::DB, $this->table(), tableExists: false, existingColumns: [], existingIndexes: []);

        $this->assertCount(1, $d->ddls);
        // DB 修飾子付きで生成される (USE 依存を避ける)
        $this->assertStringStartsWith('CREATE TABLE IF NOT EXISTS `testdb`.`oc_sitemap_lastmod`', $d->ddls[0]);
        $this->assertSame([], $d->warnings);
    }

    public function test_fully_synced_table_is_noop(): void
    {
        $d = $this->differ->diff(
            self::DB,
            $this->table(),
            tableExists: true,
            existingColumns: ['open_chat_id', 'lastmod', 'member_snapshot'],
            existingIndexes: ['PRIMARY', 'lastmod'],
        );

        $this->assertSame([], $d->ddls);
        $this->assertSame([], $d->warnings);
    }

    public function test_missing_middle_column_is_appended_without_position(): void
    {
        // member_snapshot が無い → AFTER/FIRST を付けず末尾に ADD (INSTANT ADD を妨げない)
        $d = $this->differ->diff(
            self::DB,
            $this->table(),
            tableExists: true,
            existingColumns: ['open_chat_id', 'lastmod'],
            existingIndexes: ['PRIMARY', 'lastmod'],
        );

        $this->assertCount(1, $d->ddls);
        $this->assertSame(
            'ALTER TABLE `testdb`.`oc_sitemap_lastmod` ADD COLUMN `member_snapshot` int(11) NOT NULL',
            $d->ddls[0],
        );
        $this->assertStringNotContainsString('AFTER', $d->ddls[0]);
        $this->assertStringNotContainsString('FIRST', $d->ddls[0]);
    }

    public function test_missing_first_column_is_also_appended(): void
    {
        $d = $this->differ->diff(
            self::DB,
            $this->table(),
            tableExists: true,
            existingColumns: ['lastmod', 'member_snapshot'],
            existingIndexes: ['PRIMARY', 'lastmod'],
        );

        $this->assertCount(1, $d->ddls);
        $this->assertSame(
            'ALTER TABLE `testdb`.`oc_sitemap_lastmod` ADD COLUMN `open_chat_id` int(11) NOT NULL',
            $d->ddls[0],
        );
        $this->assertStringNotContainsString('FIRST', $d->ddls[0]);
    }

    public function test_missing_index_is_added(): void
    {
        $d = $this->differ->diff(
            self::DB,
            $this->table(),
            tableExists: true,
            existingColumns: ['open_chat_id', 'lastmod', 'member_snapshot'],
            existingIndexes: ['PRIMARY'],
        );

        $this->assertCount(1, $d->ddls);
        // 索引定義原文をそのまま ADD (MySQL/MariaDB 両対応、IF NOT EXISTS 非依存)
        $this->assertSame(
            'ALTER TABLE `testdb`.`oc_sitemap_lastmod` ADD KEY `lastmod` (`lastmod`)',
            $d->ddls[0],
        );
    }

    public function test_drift_columns_and_indexes_warn_but_never_drop(): void
    {
        $d = $this->differ->diff(
            self::DB,
            $this->table(),
            tableExists: true,
            existingColumns: ['open_chat_id', 'lastmod', 'member_snapshot', 'legacy_col'],
            existingIndexes: ['PRIMARY', 'lastmod', 'legacy_idx'],
        );

        $this->assertSame([], $d->ddls); // 加算する不足は無い
        $this->assertCount(2, $d->warnings);
        $this->assertStringContainsString('column `legacy_col`', $d->warnings[0]);
        $this->assertStringContainsString('index `legacy_idx`', $d->warnings[1]);
        // PRIMARY はドリフト警告に出ない
        foreach ($d->warnings as $w) {
            $this->assertStringNotContainsString('`PRIMARY`', $w);
        }
    }

    public function test_no_ddl_ever_contains_destructive_keywords(): void
    {
        // あらゆる分岐で DROP / MODIFY / CHANGE が生成されないことの安全網
        $cases = [
            $this->differ->diff(self::DB, $this->table(), false, [], []),
            $this->differ->diff(self::DB, $this->table(), true, ['open_chat_id'], ['PRIMARY']),
            $this->differ->diff(self::DB, $this->table(), true, ['open_chat_id', 'lastmod', 'member_snapshot', 'legacy'], ['PRIMARY', 'old_idx']),
        ];

        foreach ($cases as $d) {
            foreach ($d->ddls as $ddl) {
                $this->assertDoesNotMatchRegularExpression('/\b(DROP|MODIFY|CHANGE)\b/i', $ddl);
            }
        }
    }

    public function test_all_ddls_are_schema_qualified(): void
    {
        // 接続断→再接続で USE が外れても別 DB に流れないよう、全 DDL が `db`.`table` 修飾であること
        $cases = [
            $this->differ->diff(self::DB, $this->table(), false, [], []),
            $this->differ->diff(self::DB, $this->table(), true, ['open_chat_id'], ['PRIMARY']),
        ];

        foreach ($cases as $d) {
            $this->assertNotEmpty($d->ddls);
            foreach ($d->ddls as $ddl) {
                $this->assertStringContainsString('`testdb`.`oc_sitemap_lastmod`', $ddl);
            }
        }
    }
}

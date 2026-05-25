<?php

/**
 * SchemaParser のテスト (純粋・DB不要)
 *
 * 実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Schema/test/SchemaParserTest.php
 */

declare(strict_types=1);

use App\Services\Schema\SchemaParser;
use PHPUnit\Framework\TestCase;

class SchemaParserTest extends TestCase
{
    private function parse(string $sql): array
    {
        return (new SchemaParser())->parse($sql);
    }

    public function test_ignores_non_create_table_statements(): void
    {
        $sql = <<<SQL
        -- comment line
        CREATE DATABASE IF NOT EXISTS `x` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        USE `x`;

        SET FOREIGN_KEY_CHECKS=0;

        DROP TABLE IF EXISTS `ban_room`;
        CREATE TABLE `ban_room` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `room_id` int(11) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SET FOREIGN_KEY_CHECKS=1;
        SQL;

        $tables = $this->parse($sql);
        $this->assertCount(1, $tables);
        $this->assertArrayHasKey('ban_room', $tables);
        $this->assertSame(['id', 'room_id'], $tables['ban_room']->columnOrder);
    }

    public function test_primary_key_is_not_treated_as_index(): void
    {
        $sql = <<<SQL
        CREATE TABLE `t` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB;
        SQL;

        $t = $this->parse($sql)['t'];
        $this->assertSame([], $t->indexes);
        $this->assertArrayNotHasKey('PRIMARY', $t->indexes);
    }

    public function test_unique_key_with_using_btree_and_composite(): void
    {
        $sql = <<<SQL
        CREATE TABLE `like` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `comment_id` int(11) NOT NULL,
          `user_id` varchar(64) NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `comment_id` (`comment_id`,`user_id`) USING BTREE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $t = $this->parse($sql)['like'];
        // backtick 予約語名のテーブルも解析できる
        $this->assertArrayHasKey('comment_id', $t->indexes);
        $this->assertStringContainsString('USING BTREE', $t->indexes['comment_id']);
        $this->assertStringContainsString('(`comment_id`,`user_id`)', $t->indexes['comment_id']);
        $this->assertArrayNotHasKey('PRIMARY', $t->indexes);
    }

    public function test_column_level_charset_collate_and_prefix_length_index(): void
    {
        $sql = <<<SQL
        CREATE TABLE `oc_tag` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
          `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
          PRIMARY KEY (`id`),
          KEY `tag` (`tag`(768))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $t = $this->parse($sql)['oc_tag'];
        $this->assertStringContainsString(
            'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci',
            $t->columns['name'],
        );
        $this->assertArrayHasKey('tag', $t->indexes);
        $this->assertStringContainsString('(`tag`(768))', $t->indexes['tag']);
    }

    public function test_constraint_and_foreign_key_lines_are_ignored(): void
    {
        $sql = <<<SQL
        CREATE TABLE `oc_list_user_list_show_log` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` varchar(64) NOT NULL,
          `time` datetime NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          CONSTRAINT `user_id` FOREIGN KEY (`user_id`) REFERENCES `oc_list_user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        SQL;

        $t = $this->parse($sql)['oc_list_user_list_show_log'];
        $this->assertSame(['id', 'user_id', 'time'], $t->columnOrder);
        // KEY `user_id` は索引として拾うが CONSTRAINT (同名 user_id) は拾わない
        $this->assertArrayHasKey('user_id', $t->indexes);
        $this->assertStringContainsString('KEY `user_id`', $t->indexes['user_id']);
        $this->assertStringNotContainsString('FOREIGN KEY', $t->indexes['user_id']);
    }

    public function test_create_statement_is_idempotent_form_without_trailing_semicolon(): void
    {
        $sql = <<<SQL
        CREATE TABLE `oc_sitemap_lastmod` (
          `open_chat_id` int(11) NOT NULL,
          `lastmod` datetime NOT NULL,
          `member_snapshot` int(11) NOT NULL,
          PRIMARY KEY (`open_chat_id`),
          KEY `lastmod` (`lastmod`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $create = $this->parse($sql)['oc_sitemap_lastmod']->createStatement;
        $this->assertStringStartsWith('CREATE TABLE IF NOT EXISTS `oc_sitemap_lastmod`', $create);
        $this->assertStringEndsNotWith(';', $create);
        $this->assertStringContainsString('PRIMARY KEY (`open_chat_id`)', $create);
        $this->assertStringContainsString('ENGINE=InnoDB', $create);
    }

    public function test_trailing_commas_are_stripped_from_definitions(): void
    {
        $sql = <<<SQL
        CREATE TABLE `t` (
          `a` int(11) NOT NULL,
          `b` varchar(10) NOT NULL,
          KEY `b` (`b`)
        ) ENGINE=InnoDB;
        SQL;

        $t = $this->parse($sql)['t'];
        $this->assertSame('`a` int(11) NOT NULL', $t->columns['a']);
        $this->assertSame('`b` varchar(10) NOT NULL', $t->columns['b']);
        $this->assertSame('KEY `b` (`b`)', $t->indexes['b']);
    }

    public function test_multiple_tables_in_one_file(): void
    {
        $sql = <<<SQL
        DROP TABLE IF EXISTS `a`;
        CREATE TABLE `a` (
          `id` int(11) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB;
        DROP TABLE IF EXISTS `b`;
        CREATE TABLE `b` (
          `id` int(11) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB;
        SQL;

        $tables = $this->parse($sql);
        $this->assertSame(['a', 'b'], array_keys($tables));
    }
}

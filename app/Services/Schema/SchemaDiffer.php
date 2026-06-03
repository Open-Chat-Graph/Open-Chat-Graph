<?php

declare(strict_types=1);

namespace App\Services\Schema;

use App\Services\Schema\Dto\ParsedTable;
use App\Services\Schema\Dto\TableDiff;

/**
 * スキーマ期待値 (ParsedTable) と実 DB の現状を比較し、「加算のみ」の DDL を生成する。
 *
 * 純粋クラス (DB に触れない) なので単体テスト可能。
 * 生成 DDL は CREATE TABLE IF NOT EXISTS / ADD COLUMN / ADD {索引} のみ。
 * DROP / MODIFY / CHANGE は決して生成しない (そのコードパスが無い)。
 * スキーマに無く DB にある要素 (ドリフト) は警告のみで削除しない。
 * 生成 DDL はすべて `db`.`table` 修飾 (USE のコンテキストに依存しない)。
 * 列追加は AFTER/FIRST を付けず末尾に ADD する (INSTANT ADD を妨げないため)。
 *
 * MySQL / MariaDB 両対応: ADD COLUMN / ADD KEY は実在を introspection で確認した「不足分だけ」を
 * 出すため、MariaDB 専用の `IF NOT EXISTS` (ALTER ADD) に依存しない。冪等性は diff が担保する
 * (実在する列/索引は ADD 対象に入らない)。CREATE TABLE IF NOT EXISTS は標準構文なので両対応。
 */
final class SchemaDiffer
{
    /**
     * @param string       $dbName          接続先 DB 名 (SchemaSyncRunner で `^[A-Za-z0-9_]+$` 検証済)
     * @param ParsedTable  $expected        スキーマ期待値
     * @param bool         $tableExists     実 DB にテーブルが存在するか
     * @param list<string> $existingColumns 実 DB の列名 (順不同)
     * @param list<string> $existingIndexes 実 DB の索引名 (PRIMARY を含みうる)
     */
    public function diff(
        string $dbName,
        ParsedTable $expected,
        bool $tableExists,
        array $existingColumns,
        array $existingIndexes,
    ): TableDiff {
        // 生成 DDL は DB 修飾子付き (`db`.`table`)。USE のコンテキストに依存しないため、
        // 接続断→自動再接続で USE が外れても、別 DB に DDL が流れる事故を防げる。
        $qualified = "`{$dbName}`.`{$expected->name}`";

        // テーブルごと新規 → 完全な CREATE TABLE IF NOT EXISTS 1 本で完結 (列/索引も含む)
        if (!$tableExists) {
            return new TableDiff([$this->qualifyCreate($dbName, $expected)], []);
        }

        $ddls = [];
        $warnings = [];
        $existingColSet = array_flip($existingColumns);
        $existingIdxSet = array_flip($existingIndexes);

        // --- 不足カラムを末尾に ADD (列順は問わない。AFTER/FIRST を付けないことで MySQL/
        //     新しめ MariaDB の INSTANT ADD が効きやすくなり、巨大テーブルの再構築・ロックを避ける) ---
        foreach ($expected->columnOrder as $colName) {
            if (!isset($existingColSet[$colName])) {
                $ddls[] = "ALTER TABLE {$qualified} ADD COLUMN {$expected->columns[$colName]}";
            }
        }

        // --- 不足索引を ADD (PRIMARY は別途下で扱う / CONSTRAINT は ParsedTable に含まれない) ---
        // indexDef は "UNIQUE KEY `x` (...)" 形式。ALTER TABLE ... ADD <indexDef> は MySQL/MariaDB 両対応。
        foreach ($expected->indexes as $indexName => $indexDef) {
            if (!isset($existingIdxSet[$indexName])) {
                $ddls[] = "ALTER TABLE {$qualified} ADD {$indexDef}";
            }
        }

        // --- 不足している PRIMARY KEY を ADD (スキーマが定義しており、実テーブルに PRIMARY が無い時だけ) ---
        // 「加算のみ」の方針を維持: 既存 PRIMARY の変更/削除はしない。スキーマ刷新で PRIMARY KEY を
        // 入れても sync が既存テーブルへ反映できず silent に非ユニークのまま残る不具合を防ぐ。
        // 重複値があると ADD は失敗するが、それは silent drift より明示エラーの方が安全（dry-run で事前確認可能）。
        // 旧来の非ユニーク索引 (例 `id`) が残っていても PRIMARY 追加自体は可能（冗長索引はドリフト警告で可視化）。
        if ($expected->primaryKey !== null && !isset($existingIdxSet['PRIMARY'])) {
            $ddls[] = "ALTER TABLE {$qualified} ADD {$expected->primaryKey}";
        }

        // --- ドリフト検出 (削除はしない、警告のみ) ---
        foreach ($existingColumns as $col) {
            if (!isset($expected->columns[$col])) {
                $warnings[] = "DRIFT: table `{$expected->name}` has column `{$col}` not in schema (NOT dropped)";
            }
        }
        foreach ($existingIndexes as $idx) {
            if ($idx === 'PRIMARY') {
                continue;
            }
            if (!isset($expected->indexes[$idx])) {
                $warnings[] = "DRIFT: table `{$expected->name}` has index `{$idx}` not in schema (NOT dropped)";
            }
        }

        return new TableDiff($ddls, $warnings);
    }

    /**
     * CREATE TABLE IF NOT EXISTS `name` ... を `db`.`name` 修飾に書き換える。
     * $dbName は呼び出し側 (SchemaSyncRunner) で `^[A-Za-z0-9_]+$` 検証済。
     */
    private function qualifyCreate(string $dbName, ParsedTable $expected): string
    {
        return preg_replace(
            '/^(\s*CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+)`?' . preg_quote($expected->name, '/') . '`?/i',
            '${1}`' . $dbName . '`.`' . $expected->name . '`',
            $expected->createStatement,
            1,
        ) ?? $expected->createStatement;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Schema;

use App\Services\Schema\Dto\ParsedTable;

/**
 * mysqldump 形式の MySQL/MariaDB スキーマファイルを解析し、CREATE TABLE 文だけを構造化する。
 *
 * 純粋クラス (DB に一切触れない) なので単体テスト可能。
 * `CREATE DATABASE` / `USE` / `DROP TABLE` / `SET ...` / コメント / 空行は無視する。
 *
 * 解析は **行ベース** (カンマ分割しない) で行う。mysqldump は 1 カラム / 1 索引を 1 行で
 * 出力するため、enum 値や複合索引の括弧内カンマで誤分割しない。列・索引の定義は原文を
 * そのまま保持する。
 */
final class SchemaParser
{
    /**
     * @return array<string,ParsedTable> テーブル名 => ParsedTable
     */
    public function parse(string $sql): array
    {
        $tables = [];
        $lines = preg_split('/\r\n|\r|\n/', $sql) ?: [];
        $count = count($lines);

        for ($i = 0; $i < $count;) {
            $line = trim($lines[$i]);

            // CREATE TABLE `name` ( ...
            if (!preg_match('/^CREATE\s+TABLE\s+`?([^`\s(]+)`?/i', $line, $m)) {
                $i++;
                continue;
            }

            $tableName = $m[1];
            $rawLines = [$lines[$i]];
            $bodyLines = [];
            $i++;

            // 本体: ") ENGINE..." 行まで
            while ($i < $count) {
                $raw = $lines[$i];
                $rawLines[] = $raw;
                $i++;
                if (preg_match('/^\)\s*ENGINE/i', trim($raw))) {
                    break;
                }
                $bodyLines[] = trim($raw);
            }

            [$columns, $indexes, $order, $primaryKey] = $this->parseBody($bodyLines);
            $tables[$tableName] = new ParsedTable(
                $tableName,
                $columns,
                $indexes,
                $order,
                $this->buildCreateStatement($rawLines),
                $primaryKey,
            );
        }

        return $tables;
    }

    /**
     * @param list<string> $bodyLines トリム済みの CREATE TABLE 本体行
     * @return array{0: array<string,string>, 1: array<string,string>, 2: list<string>, 3: ?string}
     */
    private function parseBody(array $bodyLines): array
    {
        $columns = [];
        $indexes = [];
        $order = [];
        $primaryKey = null;

        foreach ($bodyLines as $line) {
            $def = rtrim($line, ',');
            if ($def === '') {
                continue;
            }

            // PRIMARY KEY: 定義を保持する。新規 CREATE は本体が持つので不要だが、
            // 既存テーブルに PRIMARY が無い場合に「不足PKの追加」として ADD するために使う。
            if (preg_match('/^PRIMARY\s+KEY/i', $def)) {
                $primaryKey = $def;
                continue;
            }
            // FOREIGN KEY / CONSTRAINT は非スコープ
            if (preg_match('/^(CONSTRAINT|FOREIGN\s+KEY)/i', $def)) {
                continue;
            }
            // 索引: [UNIQUE|FULLTEXT|SPATIAL] KEY `name` (...)
            if (preg_match('/^(?:UNIQUE\s+|FULLTEXT\s+|SPATIAL\s+)?KEY\s+`?([^`\s(]+)`?/i', $def, $m)) {
                $indexes[$m[1]] = $def;
                continue;
            }
            // それ以外で先頭が `col` ... なら列定義
            if (preg_match('/^`?([^`\s]+)`?\s+/', $def, $m)) {
                $columns[$m[1]] = $def;
                $order[] = $m[1];
            }
        }

        return [$columns, $indexes, $order, $primaryKey];
    }

    /**
     * CREATE TABLE ... ) ENGINE...; を再構成し、CREATE TABLE → CREATE TABLE IF NOT EXISTS
     * に正規化、末尾セミコロンを除去。
     *
     * @param list<string> $rawLines
     */
    private function buildCreateStatement(array $rawLines): string
    {
        $stmt = rtrim(implode("\n", $rawLines));
        $stmt = rtrim($stmt, ";\n\r\t ");
        return preg_replace(
            '/^(\s*CREATE\s+TABLE)\b(?!\s+IF\s+NOT\s+EXISTS)/i',
            '$1 IF NOT EXISTS',
            $stmt,
            1,
        ) ?? $stmt;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Schema;

use App\Models\Repositories\DB;
use App\Services\Schema\Dto\SchemaSyncResult;

/**
 * 1 つの DB に対し、スキーマファイルとの差分を「加算のみ」で反映する薄い PDO 層。
 *
 * Parser / Differ (純粋) を組み合わせ、実 DB の introspection と DDL 実行だけを担う。
 * CREATE DATABASE はしない (既存 DB 前提。無ければ skip)。
 *
 * 生成 DDL は `db`.`table` 修飾なので、接続断→自動再接続で USE が外れても別 DB に流れない。
 * DDL 実行前にセッションの lock_wait_timeout を短く設定し、本番のロック競合時はハングせず
 * 即失敗する (加算・冪等なので再実行で復旧)。
 */
final class SchemaSyncRunner
{
    /**
     * DDL のロック待ちを打ち切る秒数。本番でテーブルがロック中/長時間トランザクション中でも
     * ここまでしか待たず即失敗する (待ち続けて後続クエリを詰まらせない)。
     * lock_wait_timeout の既定は MySQL 約1年 / MariaDB 1日なので、明示的に短くする。
     */
    private const LOCK_WAIT_TIMEOUT_SEC = 30;

    public function __construct(
        private readonly SchemaParser $parser = new SchemaParser(),
        private readonly SchemaDiffer $differ = new SchemaDiffer(),
    ) {}

    /**
     * @param string        $dbName     接続先 DB 名 (runtime 解決済)
     * @param string        $schemaPath スキーマ .sql の絶対パス
     * @param bool          $dryRun     true なら DDL を実行せず出力のみ
     * @param ?callable      $logger     fn(string): void。省略時は STDOUT
     */
    public function sync(string $dbName, string $schemaPath, bool $dryRun, ?callable $logger = null): SchemaSyncResult
    {
        $log = $logger ?? static fn(string $s) => fwrite(STDOUT, $s . "\n");
        $result = new SchemaSyncResult($dbName);

        // DB 名は AppConfig 由来 (信頼) だが、USE の識別子インジェクション防止に念のため検証
        if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
            throw new \InvalidArgumentException("Unsafe database name: {$dbName}");
        }

        // information_schema 経由で存在確認 (対象 DB が無くても接続できる)
        DB::$pdo = null;
        DB::connect(['dbName' => 'information_schema']);
        $exists = DB::execute(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$dbName],
        )->fetchColumn();

        if ($exists === false) {
            $log("Skipped: {$dbName} (database not found)");
            $result->skipped = true;
            DB::$pdo = null;
            return $result;
        }

        // 念のためのコンテキスト切替 (生成 DDL は `db`.`table` 修飾済なので主たる保証ではない)
        DB::$pdo->exec("USE `{$dbName}`");

        // DDL のロック待ちを短時間で打ち切り、本番のテーブルロック時にハング/詰まりを避ける。
        // dry-run は DDL を出さないので不要。
        if (!$dryRun) {
            DB::$pdo->exec('SET SESSION lock_wait_timeout = ' . self::LOCK_WAIT_TIMEOUT_SEC);
            DB::$pdo->exec('SET SESSION innodb_lock_wait_timeout = ' . self::LOCK_WAIT_TIMEOUT_SEC);
        }

        $tables = $this->parser->parse((string)file_get_contents($schemaPath));

        $existingTables = array_flip(DB::execute(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$dbName],
        )->fetchAll(\PDO::FETCH_COLUMN));

        foreach ($tables as $tableName => $parsed) {
            $tableExists = isset($existingTables[$tableName]);
            $cols = $tableExists ? $this->columnNames($dbName, $tableName) : [];
            $idxs = $tableExists ? $this->indexNames($dbName, $tableName) : [];

            $diff = $this->differ->diff($dbName, $parsed, $tableExists, $cols, $idxs);

            foreach ($diff->warnings as $w) {
                $log("[WARN] {$w}");
                $result->warnings[] = $w;
            }

            foreach ($diff->ddls as $ddl) {
                if ($dryRun) {
                    $log('[DRY-RUN] ' . $this->oneLine($ddl) . ';');
                } else {
                    DB::execute($ddl);
                    $log('[APPLIED] ' . $this->oneLine($ddl) . ';');
                }
                $result->appliedDdls[] = $ddl;
                $this->tally($result, $ddl, $tableExists);
            }
        }

        $log($result->summaryLine());
        DB::$pdo = null; // 次の DB / 後続処理へ接続コンテキストを持ち越さない
        return $result;
    }

    /** @return list<string> */
    private function columnNames(string $db, string $table): array
    {
        return DB::execute(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$db, $table],
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** @return list<string> */
    private function indexNames(string $db, string $table): array
    {
        return DB::execute(
            'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$db, $table],
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function tally(SchemaSyncResult $result, string $ddl, bool $tableExists): void
    {
        if (!$tableExists) {
            $result->createdTables++;
        } elseif (preg_match('/\bADD COLUMN\b/i', $ddl)) {
            $result->addedColumns++;
        } else {
            $result->addedIndexes++;
        }
    }

    private function oneLine(string $ddl): string
    {
        return preg_replace('/\s+/', ' ', trim($ddl)) ?? $ddl;
    }
}

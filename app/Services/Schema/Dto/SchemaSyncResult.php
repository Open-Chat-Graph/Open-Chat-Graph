<?php

declare(strict_types=1);

namespace App\Services\Schema\Dto;

/**
 * 1 DB の同期結果サマリ。CLI ログ集約とテストの assert を容易にする。
 */
final class SchemaSyncResult
{
    /** DB が存在せずスキップしたか */
    public bool $skipped = false;

    /** 実行した (dry-run では実行予定の) DDL */
    /** @var list<string> */
    public array $appliedDdls = [];

    /** ドリフト警告 */
    /** @var list<string> */
    public array $warnings = [];

    public int $createdTables = 0;
    public int $addedColumns = 0;
    public int $addedIndexes = 0;

    public function __construct(public readonly string $dbName) {}

    public function summaryLine(): string
    {
        return sprintf(
            '%s: +%d table, +%d column, +%d index, %d warning%s',
            $this->dbName,
            $this->createdTables,
            $this->addedColumns,
            $this->addedIndexes,
            count($this->warnings),
            $this->skipped ? ' (SKIPPED: db not found)' : '',
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Schema\Dto;

/**
 * 1 テーブルについて、実 DB をスキーマに近づけるための「加算のみ」DDL と、
 * スキーマに存在しない要素 (ドリフト) の警告。
 *
 * ddls は CREATE TABLE IF NOT EXISTS / ADD COLUMN / ADD {索引} のみを含み (いずれも `db`.`table`
 * 修飾)、DROP / MODIFY / CHANGE は構造的に含まれない。
 */
final class TableDiff
{
    /**
     * @param list<string> $ddls     実行すべき加算 DDL
     * @param list<string> $warnings ドリフト警告 (削除はしない、人間向け通知のみ)
     */
    public function __construct(
        public readonly array $ddls,
        public readonly array $warnings,
    ) {}
}

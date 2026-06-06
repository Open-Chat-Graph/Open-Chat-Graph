<?php

declare(strict_types=1);

namespace App\Services\Schema\Dto;

/**
 * スキーマファイル (mysqldump 形式) から解析した 1 テーブルの期待構造。
 *
 * 列・索引の定義文字列はスキーマファイル原文をそのまま保持する (末尾カンマのみ除去)。
 * これにより MariaDB が受理する正しい構文を再構築なしで ADD できる。
 */
final class ParsedTable
{
    /**
     * @param string                $name            テーブル名 (バッククォート除去済)
     * @param array<string,string>  $columns         列名 => 列定義原文 (例: "`member` int(11) NOT NULL")
     * @param array<string,string>  $indexes         索引名 => 索引定義原文 (PRIMARY/CONSTRAINT は含めない)
     * @param list<string>          $columnOrder     スキーマ上の列出現順 (AFTER 句生成に使う)
     * @param string                $createStatement IF NOT EXISTS 化した完全な CREATE TABLE 文 (末尾セミコロン除去)
     * @param ?string               $primaryKey      PRIMARY KEY 定義原文 (例: "PRIMARY KEY (`id`)")。無ければ null。
     *                                                既存テーブルに PRIMARY が無い場合の「不足PKの追加」に使う。
     */
    public function __construct(
        public readonly string $name,
        public readonly array $columns,
        public readonly array $indexes,
        public readonly array $columnOrder,
        public readonly string $createStatement,
        public readonly ?string $primaryKey = null,
    ) {}
}

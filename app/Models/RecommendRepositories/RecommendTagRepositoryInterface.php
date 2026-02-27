<?php

declare(strict_types=1);

namespace App\Models\RecommendRepositories;

/**
 * タグマッチング結果のDB読み書きを担当するリポジトリ
 */
interface RecommendTagRepositoryInterface
{
    /**
     * 対象行を1 SELECTで取得
     *
     * @return array<int, array{id: int, name: string, description: string, category: int}>
     */
    function fetchTargetRows(string $targetIdJoinClause, string $start, string $end): array;

    /**
     * modify_recommend テーブルの管理者オーバーライドを取得
     *
     * @param int[] $ids
     * @return array<int, string> id => tag
     */
    function fetchModifyRecommendByIds(array $ids): array;

    /**
     * 一時テーブル経由でバッチINSERT + アトミックスワップ
     *
     * @param string $targetTable 対象テーブル名（recommend, oc_tag, oc_tag2）
     * @param array<int, string> $data id => tag のマッピング
     */
    function bulkInsertViaTemp(string $targetTable, array $data): void;

    /**
     * Mock環境用：処理対象IDを制限する一時テーブルを作成
     */
    function createTargetIdTable(string $start, string $end, int $limit): void;

    /**
     * Mock環境用：一時テーブルを削除
     */
    function dropTargetIdTable(): void;
}

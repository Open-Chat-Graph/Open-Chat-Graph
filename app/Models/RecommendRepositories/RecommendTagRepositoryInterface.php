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
     * シャドウテーブル方式でフル再構築結果を反映する。
     *
     * live をコピーして種にし、マッチ結果を upsert で上書きした後、
     * RENAME で一括スワップする（削除済み/非マッチ/管理者行を保持しつつ原子的に切替）。
     *
     * @param string $targetTable 対象テーブル名（recommend, oc_tag, oc_tag2 のみ許可）
     * @param array<int, string> $data id => tag のマッピング（全件マッチ結果）
     */
    function applyViaShadowSwap(string $targetTable, array $data): void;

    /**
     * modify_recommend（管理者オーバーライド）の全行を recommend へ upsert で再適用する。
     * 空でも安全に動作する。
     */
    function reapplyAllModifyRecommend(): void;

    /**
     * Mock環境用：処理対象IDを制限する一時テーブルを作成
     */
    function createTargetIdTable(string $start, string $end, int $limit): void;

    /**
     * Mock環境用：一時テーブルを削除
     */
    function dropTargetIdTable(): void;
}

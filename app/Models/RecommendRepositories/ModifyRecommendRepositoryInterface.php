<?php

declare(strict_types=1);

namespace App\Models\RecommendRepositories;

/**
 * 管理者がオープンチャットのタグを手動で上書き・削除する操作のリポジトリ
 */
interface ModifyRecommendRepositoryInterface
{
    /**
     * @return string|false 手動設定されたタグ。未設定の場合はfalse
     */
    function getModifyTag(int $id): string|false;

    /**
     * タグの手動上書きを登録する（modify_recommend）
     */
    function upsertModifyTag(int $id, string $tag): void;

    /**
     * 推薦タグを登録する（recommend）
     */
    function upsertRecommendTag(int $id, string $tag): void;

    /**
     * 推薦タグを削除する（recommend）
     */
    function deleteRecommendTag(int $id): void;

    /**
     * タグの手動上書きを削除する（modify_recommend）
     */
    function deleteModifyTag(int $id): void;
}

<?php

declare(strict_types=1);

namespace App\Models\RecommendRepositories;

interface RecommendRankingRepositoryInterface
{
    /**
     * エンティティでフィルタしたランキングを取得
     *
     * @param string $entity タグ、カテゴリ、エンブレムなど
     * @param string $table ランキングテーブル名
     * @param int $minDiffMember 最小メンバー増減数
     * @param int $limit 取得件数
     * @return array ランキングデータ
     */
    function getRanking(
        string $entity,
        string $table,
        int $minDiffMember,
        int $limit,
    ): array;

    /**
     * 除外IDを指定してランキングを取得
     *
     * @param string $entity タグ、カテゴリ、エンブレムなど
     * @param string $table ランキングテーブル名
     * @param int $minDiffMember 最小メンバー増減数
     * @param array $idArray 結果から除外するID
     * @param int $limit 取得件数
     * @return array ランキングデータ
     */
    function getRankingByExceptId(
        string $entity,
        string $table,
        int $minDiffMember,
        array $idArray,
        int $limit,
    ): array;

    /**
     * メンバー数順でリストを取得
     *
     * @param string $entity タグ、カテゴリ、エンブレムなど
     * @param array $idArray 結果から除外するID
     * @param int $limit 取得件数
     * @return array ランキングデータ
     */
    function getListOrderByMemberDesc(
        string $entity,
        array $idArray,
        int $limit,
    ): array;

    /**
     * IDリストからrecommendタグを取得
     *
     * @param int[] $idArray
     * @return string[]
     */
    function getRecommendTags(array $idArray): array;

    /**
     * IDリストからoc_tagを取得
     *
     * @param int[] $idArray
     * @return string[]
     */
    function getOcTags(array $idArray): array;
}

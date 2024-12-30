<?php

declare(strict_types=1);

namespace App\Models\RecommendRepositories;

interface RecommendRankingRepositoryInterface
{
    const SelectPage = "
        oc.id,
        oc.name,
        oc.local_img_url AS img_url,
        oc.img_url AS api_img_url,
        oc.member,
        oc.description,
        oc.emblem,
        oc.category,
        oc.emid,
        oc.url,
        oc.api_created_at,
        oc.created_at,
        oc.updated_at,
        oc.join_method_type,
        ranking.tag1,
        ranking.tag2
    ";

    function getRanking(
        string $entity,
        string $table,
        int $minDiffMember,
        int $limit,
    ): array;

    /**
     * @param array $idArray 結果から除外するID
     */
    function getRankingByExceptId(
        string $entity,
        string $table,
        int $minDiffMember,
        array $idArray,
        int $limit,
    ): array;

    /**
     * @param array $idArray 結果から除外するID
     */
    function getListOrderByMemberDesc(
        string $entity,
        array $idArray,
        int $limit,
    ): array;
}

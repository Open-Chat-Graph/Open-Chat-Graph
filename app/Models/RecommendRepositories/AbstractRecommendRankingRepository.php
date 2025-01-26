<?php

declare(strict_types=1);

namespace App\Models\RecommendRepositories;

use App\Models\Repositories\DB;

abstract class AbstractRecommendRankingRepository
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

    abstract function getRanking(
        string $entity,
        string $table,
        int $minDiffMember,
        int $limit,
    ): array;

    /**
     * @param array $idArray 結果から除外するID
     */
    abstract function getRankingByExceptId(
        string $entity,
        string $table,
        int $minDiffMember,
        array $idArray,
        int $limit,
    ): array;

    /**
     * @param array $idArray 結果から除外するID
     */
    abstract function getListOrderByMemberDesc(
        string $entity,
        array $idArray,
        int $limit,
    ): array;

    /**
     * @param int[] $idArray
     * @return string[]
     */
    function getRecommendTags(
        array $idArray,
    ): array {
        return $this->getTagsFromId($idArray, 'recommend');
    }

    /**
     * @param int[] $idArray
     * @return string[]
     */
    function getOcTags(
        array $idArray,
    ): array {
        return $this->getTagsFromId($idArray, 'oc_tag');
    }

    /**
     * @param int[] $idArray
     * @return string[]
     */
    protected function getTagsFromId(
        array $idArray,
        string $table,
    ): array {
        $ids = implode(",", $idArray) ?: 0;
        return DB::fetchAll(
            "SELECT
                tag
            FROM
                {$table}
            WHERE
                id IN ({$ids})",
            args: [\PDO::FETCH_COLUMN]
        );
    }
}

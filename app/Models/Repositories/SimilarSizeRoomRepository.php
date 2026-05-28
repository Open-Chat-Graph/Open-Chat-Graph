<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Models\Repositories\DB;

class SimilarSizeRoomRepository implements SimilarSizeRoomRepositoryInterface
{
    private const LIMIT = 5;

    /**
     * open_chat と statistics_ranking_hour / hour24 を join した共通 SELECT 句。
     * 取得カラムは similar_size_rooms_list.php (View) が消費する形に揃える。
     */
    private const SELECT_FIELDS =
        "SELECT
            oc.id, oc.name, oc.img_url, oc.description, oc.member,
            oc.emblem, oc.api_created_at, oc.category, oc.emid,
            oc.join_method_type,
            rh.diff_member AS rh_diff_member,
            rh.percent_increase AS rh_percent_increase,
            rh24.diff_member AS rh24_diff_member,
            rh24.percent_increase AS rh24_percent_increase";

    public function findByTagWithMemberRange(int $excludeId, int $currentMember, string $tag, int $minMember, int $maxMember): array
    {
        $sql = self::SELECT_FIELDS . "
            FROM open_chat AS oc
            INNER JOIN recommend AS r ON oc.id = r.id AND r.tag = :tag
            LEFT JOIN statistics_ranking_hour AS rh ON oc.id = rh.open_chat_id
            LEFT JOIN statistics_ranking_hour24 AS rh24 ON oc.id = rh24.open_chat_id
            WHERE oc.member BETWEEN :minM AND :maxM
              AND oc.id != :excludeId
            ORDER BY ABS(oc.member - :currentMember) ASC, oc.member DESC
            LIMIT " . self::LIMIT;

        return DB::fetchAll($sql, [
            'tag'           => $tag,
            'minM'          => $minMember,
            'maxM'          => $maxMember,
            'excludeId'     => $excludeId,
            'currentMember' => $currentMember,
        ]);
    }

    public function findByCategoryWithMemberRange(int $excludeId, int $currentMember, int $category, int $minMember, int $maxMember): array
    {
        $sql = self::SELECT_FIELDS . "
            FROM open_chat AS oc
            LEFT JOIN statistics_ranking_hour AS rh ON oc.id = rh.open_chat_id
            LEFT JOIN statistics_ranking_hour24 AS rh24 ON oc.id = rh24.open_chat_id
            WHERE oc.category = :category
              AND oc.member BETWEEN :minM AND :maxM
              AND oc.id != :excludeId
            ORDER BY ABS(oc.member - :currentMember) ASC, oc.member DESC
            LIMIT " . self::LIMIT;

        return DB::fetchAll($sql, [
            'category'      => $category,
            'minM'          => $minMember,
            'maxM'          => $maxMember,
            'excludeId'     => $excludeId,
            'currentMember' => $currentMember,
        ]);
    }
}

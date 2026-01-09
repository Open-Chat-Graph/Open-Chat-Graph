<?php

declare(strict_types=1);

namespace App\Models\RecommendRepositories;

use App\Models\Repositories\DB;

/**
 * JOIN順序を最適化したリポジトリ
 * 先にtagで絞り込んでからJOINすることで、処理対象を削減
 */
class OptimizedRecommendRankingRepository extends AbstractRecommendRankingRepository
{
    function getRanking(
        string $tag,
        string $table,
        int $minDiffMember,
        int $limit,
    ): array {
        $select = self::SelectPage;
        return DB::fetchAll(
            "SELECT
                {$select},
                '{$table}' AS table_name
            FROM
                (
                    SELECT
                        t2.id,
                        t1.diff_member AS diff_member,
                        t3.tag AS tag1,
                        t4.tag AS tag2
                    FROM
                        (SELECT id FROM recommend WHERE tag = :tag) AS t2
                        INNER JOIN {$table} AS t1 ON t1.open_chat_id = t2.id AND t1.diff_member >= :minDiffMember
                        LEFT JOIN (SELECT * FROM oc_tag GROUP BY id LIMIT 1) AS t3 ON t2.id = t3.id
                        LEFT JOIN (SELECT * FROM oc_tag2 GROUP BY id LIMIT 1) AS t4 ON t2.id = t4.id
                ) AS ranking
                JOIN open_chat AS oc ON oc.id = ranking.id
            ORDER BY
                ranking.diff_member DESC
            LIMIT
                :limit",
            compact('tag', 'limit', 'minDiffMember')
        );
    }

    function getRankingByExceptId(
        string $tag,
        string $table,
        int $minDiffMember,
        array $idArray,
        int $limit,
    ): array {
        $ids = implode(",", $idArray) ?: 0;
        $select = self::SelectPage;
        return DB::fetchAll(
            "SELECT
                {$select},
                '{$table}' AS table_name
            FROM
                (
                    SELECT
                        t2.id,
                        t1.diff_member AS diff_member,
                        t3.tag AS tag1,
                        t4.tag AS tag2
                    FROM
                        (SELECT id FROM recommend WHERE tag = :tag AND id NOT IN ({$ids})) AS t2
                        INNER JOIN {$table} AS t1 ON t1.open_chat_id = t2.id AND t1.diff_member >= :minDiffMember
                        LEFT JOIN (SELECT * FROM oc_tag GROUP BY id LIMIT 1) AS t3 ON t2.id = t3.id
                        LEFT JOIN (SELECT * FROM oc_tag2 GROUP BY id LIMIT 1) AS t4 ON t2.id = t4.id
                ) AS ranking
                JOIN open_chat AS oc ON oc.id = ranking.id
                LEFT JOIN statistics_ranking_hour AS rh ON rh.open_chat_id = oc.id
            ORDER BY
                rh.diff_member DESC, ranking.diff_member DESC
            LIMIT
                :limit",
            compact('tag', 'limit', 'minDiffMember')
        );
    }

    function getListOrderByMemberDesc(
        string $tag,
        array $idArray,
        int $limit,
    ): array {
        $ids = implode(",", $idArray) ?: 0;
        $select = self::SelectPage;
        return DB::fetchAll(
            "SELECT
                t1.*
            FROM
                (
                    SELECT
                        {$select},
                        'open_chat' AS table_name
                    FROM
                        (
                            SELECT
                                r.id,
                                t3.tag AS tag1,
                                t4.tag AS tag2
                            FROM
                                recommend AS r
                                LEFT JOIN (SELECT * FROM oc_tag GROUP BY id LIMIT 1) AS t3 ON r.id = t3.id
                                LEFT JOIN (SELECT * FROM oc_tag2 GROUP BY id LIMIT 1) AS t4 ON r.id = t4.id
                            WHERE
                                r.tag = :tag
                                AND r.id NOT IN ({$ids})
                        ) AS ranking
                        JOIN open_chat AS oc ON oc.id = ranking.id
                        LEFT JOIN statistics_ranking_hour24 AS rh ON oc.id = rh.open_chat_id
                        LEFT JOIN statistics_ranking_hour AS rh2 ON oc.id = rh2.open_chat_id
                    WHERE
                        ((rh.open_chat_id IS NOT NULL OR rh2.open_chat_id IS NOT NULL) OR oc.member >= 15)
                    ORDER BY
                        oc.member DESC
                    LIMIT
                        :limit
                ) AS t1
                LEFT JOIN statistics_ranking_hour AS t2 ON t1.id = t2.open_chat_id
            ORDER BY
                t2.diff_member DESC, t1.member DESC",
            compact('tag', 'limit')
        );
    }

    function getRecommendTag(int $id): string|false
    {
        return DB::fetchColumn("SELECT tag FROM recommend WHERE id = {$id}");
    }

    /** @return array{0:string|false,1:string|false} */
    function getTags(int $id): array
    {
        $tag = DB::fetchColumn("SELECT tag FROM oc_tag WHERE id = {$id}");
        $tag2 = DB::fetchColumn("SELECT tag FROM oc_tag2 WHERE id = {$id}");
        return [$tag, $tag2];
    }
}

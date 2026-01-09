<?php

declare(strict_types=1);

namespace App\Models\RecommendRepositories;

use App\Models\Repositories\DB;

/**
 * 1タグ1クエリで全ランキングデータを取得する最適化版リポジトリ
 */
class BatchRecommendRankingRepository extends AbstractRecommendRankingRepository
{
    /**
     * 4つのランキング（hour, day, week, member）を1つのクエリで取得
     *
     * @param string $tag タグ名
     * @param int $hourMinDiff hourランキングの最小メンバー増減数
     * @param int $dayMinDiff dayランキングの最小メンバー増減数
     * @param int $weekMinDiff weekランキングの最小メンバー増減数
     * @param int $limit 各ランキングの最大取得件数
     * @param int $memberLimit memberランキングの取得件数
     * @return array{hour:array,day:array,week:array,member:array}
     */
    function getRankingBatch(
        string $tag,
        int $hourMinDiff,
        int $dayMinDiff,
        int $weekMinDiff,
        int $limit,
        int $memberLimit
    ): array {
        $select = self::SelectPage;

        $results = DB::fetchAll(
            "WITH hour_ranking AS (
                SELECT
                    t2.id,
                    t1.diff_member,
                    t3.tag AS tag1,
                    t4.tag AS tag2
                FROM
                    recommend AS t2
                    JOIN (
                        SELECT
                            *
                        FROM
                            statistics_ranking_hour
                        WHERE
                            diff_member >= :hourMinDiff
                    ) AS t1 ON t1.open_chat_id = t2.id
                    LEFT JOIN (SELECT * FROM oc_tag GROUP BY id LIMIT 1) AS t3 ON t1.open_chat_id = t3.id
                    LEFT JOIN (SELECT * FROM oc_tag2 GROUP BY id LIMIT 1) AS t4 ON t1.open_chat_id = t4.id
                WHERE
                    t2.tag = :tag
                ORDER BY
                    t1.diff_member DESC
                LIMIT
                    :limit
            ),
            day_ranking AS (
                SELECT
                    t2.id,
                    t1.diff_member,
                    t3.tag AS tag1,
                    t4.tag AS tag2
                FROM
                    recommend AS t2
                    JOIN (
                        SELECT
                            *
                        FROM
                            statistics_ranking_hour24
                        WHERE
                            diff_member >= :dayMinDiff
                    ) AS t1 ON t1.open_chat_id = t2.id
                    LEFT JOIN (SELECT * FROM oc_tag GROUP BY id LIMIT 1) AS t3 ON t1.open_chat_id = t3.id
                    LEFT JOIN (SELECT * FROM oc_tag2 GROUP BY id LIMIT 1) AS t4 ON t1.open_chat_id = t4.id
                WHERE
                    t2.tag = :tag
                    AND t2.id NOT IN (SELECT id FROM hour_ranking)
                ORDER BY
                    t1.diff_member DESC
                LIMIT
                    :limit
            ),
            week_ranking AS (
                SELECT
                    t2.id,
                    t1.diff_member,
                    t3.tag AS tag1,
                    t4.tag AS tag2
                FROM
                    recommend AS t2
                    JOIN (
                        SELECT
                            *
                        FROM
                            statistics_ranking_week
                        WHERE
                            diff_member >= :weekMinDiff
                    ) AS t1 ON t1.open_chat_id = t2.id
                    LEFT JOIN (SELECT * FROM oc_tag GROUP BY id LIMIT 1) AS t3 ON t1.open_chat_id = t3.id
                    LEFT JOIN (SELECT * FROM oc_tag2 GROUP BY id LIMIT 1) AS t4 ON t1.open_chat_id = t4.id
                WHERE
                    t2.tag = :tag
                    AND t2.id NOT IN (
                        SELECT id FROM hour_ranking
                        UNION ALL
                        SELECT id FROM day_ranking
                    )
                ORDER BY
                    t1.diff_member DESC
                LIMIT
                    :limit
            ),
            member_ranking AS (
                SELECT
                    r.id,
                    NULL AS diff_member,
                    t3.tag AS tag1,
                    t4.tag AS tag2
                FROM
                    recommend AS r
                    LEFT JOIN (SELECT * FROM oc_tag GROUP BY id LIMIT 1) AS t3 ON r.id = t3.id
                    LEFT JOIN (SELECT * FROM oc_tag2 GROUP BY id LIMIT 1) AS t4 ON r.id = t4.id
                    JOIN open_chat AS oc ON oc.id = r.id
                    LEFT JOIN statistics_ranking_hour24 AS rh ON oc.id = rh.open_chat_id
                    LEFT JOIN statistics_ranking_hour AS rh2 ON oc.id = rh2.open_chat_id
                WHERE
                    r.tag = :tag
                    AND r.id NOT IN (
                        SELECT id FROM hour_ranking
                        UNION ALL
                        SELECT id FROM day_ranking
                        UNION ALL
                        SELECT id FROM week_ranking
                    )
                    AND ((rh.open_chat_id IS NOT NULL OR rh2.open_chat_id IS NOT NULL) OR oc.member >= 15)
                ORDER BY
                    oc.member DESC
                LIMIT
                    :memberLimit
            )
            SELECT
                {$select},
                'statistics_ranking_hour' AS table_name,
                'hour' AS source,
                ranking.diff_member AS sort_diff_member
            FROM
                hour_ranking AS ranking
                JOIN open_chat AS oc ON oc.id = ranking.id

            UNION ALL

            SELECT
                {$select},
                'statistics_ranking_hour24' AS table_name,
                'day' AS source,
                ranking.diff_member AS sort_diff_member
            FROM
                day_ranking AS ranking
                JOIN open_chat AS oc ON oc.id = ranking.id

            UNION ALL

            SELECT
                {$select},
                'statistics_ranking_week' AS table_name,
                'week' AS source,
                ranking.diff_member AS sort_diff_member
            FROM
                week_ranking AS ranking
                JOIN open_chat AS oc ON oc.id = ranking.id

            UNION ALL

            SELECT
                {$select},
                'open_chat' AS table_name,
                'member' AS source,
                NULL AS sort_diff_member
            FROM
                member_ranking AS ranking
                JOIN open_chat AS oc ON oc.id = ranking.id",
            compact('tag', 'hourMinDiff', 'dayMinDiff', 'weekMinDiff', 'limit', 'memberLimit')
        );

        // sourceカラムでグループ化
        $grouped = ['hour' => [], 'day' => [], 'week' => [], 'member' => []];
        foreach ($results as $row) {
            $source = $row['source'];
            unset($row['source']);
            $grouped[$source][] = $row;
        }

        return $grouped;
    }

    // 互換性のため既存メソッドも実装（未使用になる予定）
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
                        recommend AS t2
                        JOIN (
                            SELECT
                                *
                            FROM
                                {$table}
                            WHERE
                                diff_member >= :minDiffMember
                        ) AS t1 ON t1.open_chat_id = t2.id
                        LEFT JOIN (SELECT * FROM oc_tag GROUP BY id LIMIT 1) AS t3 ON t1.open_chat_id = t3.id
                        LEFT JOIN (SELECT * FROM oc_tag2 GROUP BY id LIMIT 1) AS t4 ON t1.open_chat_id = t4.id
                    WHERE
                        t2.tag = :tag
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
                        recommend AS t2
                        JOIN (
                            SELECT
                                sr1.*
                            FROM
                                (
                                    SELECT
                                        *
                                    FROM
                                        {$table}
                                    WHERE
                                        diff_member >= :minDiffMember
                                ) AS sr1
                        ) AS t1 ON t1.open_chat_id = t2.id
                        LEFT JOIN (SELECT * FROM oc_tag GROUP BY id LIMIT 1) AS t3 ON t1.open_chat_id = t3.id
                        LEFT JOIN (SELECT * FROM oc_tag2 GROUP BY id LIMIT 1) AS t4 ON t1.open_chat_id = t4.id
                    WHERE
                        t2.tag = :tag
                ) AS ranking
                JOIN open_chat AS oc ON oc.id = ranking.id
                LEFT JOIN statistics_ranking_hour AS rh ON rh.open_chat_id = oc.id
            WHERE
                oc.id NOT IN ({$ids})
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
                                r.*,
                                t3.tag AS tag1,
                                t4.tag AS tag2
                            FROM
                                (
                                    SELECT
                                        *
                                    FROM
                                        recommend
                                    WHERE
                                        tag = :tag
                                ) AS r
                                LEFT JOIN (SELECT * FROM oc_tag GROUP BY id LIMIT 1) AS t3 ON r.id = t3.id
                                LEFT JOIN (SELECT * FROM oc_tag2 GROUP BY id LIMIT 1) AS t4 ON r.id = t4.id
                        ) AS ranking
                        JOIN open_chat AS oc ON oc.id = ranking.id
                        LEFT JOIN statistics_ranking_hour24 AS rh ON oc.id = rh.open_chat_id
                        LEFT JOIN statistics_ranking_hour AS rh2 ON oc.id = rh2.open_chat_id
                    WHERE
                        oc.id NOT IN ({$ids})
                        AND ((rh.open_chat_id IS NOT NULL OR rh2.open_chat_id IS NOT NULL) OR oc.member >= 15)
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

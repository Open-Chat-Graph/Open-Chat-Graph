<?php

declare(strict_types=1);

namespace App\Models\RecommendRepositories;

use App\Models\Repositories\DB;

class RecommendRankingRepository implements RecommendRankingRepositoryInterface
{
    function getRanking(
        string $tag,
        string $table,
        int $minDiffMember,
        int $limit,
    ): array {
        $select = RecommendRankingRepositoryInterface::SelectPage;
        return DB::fetchAll(
            "SELECT
                {$select},
                '{$table}' AS table_name
            FROM
                open_chat AS oc
                JOIN (
                    SELECT
                        t2.id,
                        t1.diff_member AS diff_member,
                        t3.tag AS tag1,
                        t4.tag AS tag2
                    FROM
                        (
                            SELECT
                                *
                            FROM
                                {$table}
                            WHERE
                                diff_member >= :minDiffMember
                        ) AS t1
                        JOIN recommend AS t2 ON t1.open_chat_id = t2.id
                        LEFT JOIN oc_tag AS t3 ON t1.open_chat_id = t3.id
                        LEFT JOIN oc_tag2 AS t4 ON t1.open_chat_id = t4.id
                    WHERE
                        t2.tag = :tag
                ) AS ranking ON oc.id = ranking.id
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
        $select = RecommendRankingRepositoryInterface::SelectPage;
        return DB::fetchAll(
            "SELECT
                {$select},
                '{$table}' AS table_name
            FROM
                open_chat AS oc
                JOIN (
                    SELECT
                        t2.id,
                        t1.diff_member AS diff_member,
                        t3.tag AS tag1,
                        t4.tag AS tag2
                    FROM
                        (
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
                        ) AS t1
                        JOIN recommend AS t2 ON t1.open_chat_id = t2.id
                        LEFT JOIN oc_tag AS t3 ON t1.open_chat_id = t3.id
                        LEFT JOIN oc_tag2 AS t4 ON t1.open_chat_id = t4.id
                    WHERE
                        t2.tag = :tag
                ) AS ranking ON oc.id = ranking.id
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
        $select = RecommendRankingRepositoryInterface::SelectPage;
        return DB::fetchAll(
            "SELECT
                t1.*
            FROM
                (
                    SELECT
                        {$select},
                        'open_chat' AS table_name
                    FROM
                        open_chat AS oc
                        JOIN (
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
                                LEFT JOIN oc_tag AS t3 ON r.id = t3.id
                                LEFT JOIN oc_tag2 AS t4 ON r.id = t4.id
                        ) AS ranking ON oc.id = ranking.id
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

    /** @return array{ hour:?int,hour24:?int,week:?int } */
    function getTagDiffMember(string $tag)
    {
        $query =
            "SELECT
                sum(t2.diff_member) AS `hour`,
                sum(t3.diff_member) AS hour24,
                sum(t5.diff_member) AS `week`
            FROM
                (SELECT tag, id FROM recommend WHERE tag = :tag) AS t1
                LEFT JOIN statistics_ranking_hour24 AS t3 ON t1.id = t3.open_chat_id
                LEFT JOIN statistics_ranking_hour AS t2 ON t1.id = t2.open_chat_id
                LEFT JOIN statistics_ranking_week AS t5 ON t1.id = t5.open_chat_id
            WHERE
                t3.open_chat_id IS NOT NULL 
                OR t2.open_chat_id IS NOT NULL
            GROUP BY
                t1.tag";

        return DB::fetch($query, compact('tag'));
    }

    /** @return array<int, array<array{tag:string,record_count:int,hour:?int,hour24:?int,week:?int}>> カテゴリーに基づいてグループ化された結果 */
    function getRecommendTagAndCategoryAll()
    {
        $query =
            "SELECT
                grouped_data.tag,
                grouped_data.category,
                max_counts.sumcnt AS record_count,
                max_counts.total_member_sum AS total_member
            FROM
                (
                    SELECT
                        r.tag,
                        oc.category,
                        COUNT(*) AS cnt,
                        SUM(oc.member) AS total_member
                    FROM
                        open_chat AS oc
                        JOIN recommend AS r ON r.id = oc.id
                        LEFT JOIN statistics_ranking_hour24 AS d ON d.open_chat_id = oc.id
                        LEFT JOIN statistics_ranking_hour AS d2 ON d2.open_chat_id = oc.id
                    WHERE
                        d.open_chat_id IS NOT NULL OR d2.open_chat_id IS NOT NULL
                    GROUP BY
                        r.tag,
                        oc.category
                ) AS grouped_data
                JOIN (
                    SELECT
                        inner_counts.tag,
                        MAX(inner_counts.cnt) AS maxcnt,
                        SUM(inner_counts.cnt) AS sumcnt,
                        SUM(inner_counts.total_member) AS total_member_sum
                    FROM
                        (
                            SELECT
                                r.tag,
                                oc.category,
                                COUNT(*) AS cnt,
                                SUM(oc.member) AS total_member
                            FROM
                                open_chat AS oc 
                                JOIN recommend AS r ON r.id = oc.id
                                LEFT JOIN statistics_ranking_hour24 AS d ON d.open_chat_id = oc.id
                                LEFT JOIN statistics_ranking_hour AS d2 ON d2.open_chat_id = oc.id
                            WHERE
                                d.open_chat_id IS NOT NULL OR d2.open_chat_id IS NOT NULL
                            GROUP BY
                                r.tag,
                                oc.category
                        ) AS inner_counts
                    GROUP BY
                        inner_counts.tag
                ) AS max_counts ON grouped_data.tag = max_counts.tag
                AND grouped_data.cnt = max_counts.maxcnt";

        $results = DB::fetchAll($query);

        // 結果を整形
        $groupedResults = [];
        foreach ($results as $row) {
            // categoryをキーとして使用
            $key = $row['category'] ?? 0;
            if (!isset($groupedResults[$key])) {
                $groupedResults[$key] = [];
            }
            // 同じカテゴリーのデータを配列に追加
            $groupedResults[$key][] = [...$row, ...$this->getTagDiffMember($row['tag'])];
        }

        foreach ($groupedResults as &$row) {
            // $groupedResultsの要素を要素数が多い順にソート
            uasort($row, function ($a, $b) {
                return $b['week'] - $a['week'];
            });
        }

        return $groupedResults;
    }

    /** @return array{ tag:string,record_count:int } */
    function getRecommendTagRecordCountAllRoom()
    {
        $query =
            'SELECT
                r.tag,
                COUNT(*) AS record_count
            FROM
                open_chat AS oc
                JOIN recommend AS r ON r.id = oc.id
            GROUP BY
                r.tag';

        return DB::fetchAll($query);
    }
}

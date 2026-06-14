<?php

declare(strict_types=1);

namespace App\Models\RecommendRepositories;

use App\Config\AppConfig;
use App\Models\Repositories\DB;

/**
 * おすすめ/カテゴリ/公式ランキングのデータ取得。
 *
 * tag/category/official のいずれも「母集団(24hランキング記録あり or member下限以上)を
 * 24時間増(記録なし=0扱い) → メンバー数 → id の単一基準で並べる」1クエリに統一している。
 * 先頭 LIST_LIMIT_RECOMMEND 件が表示、全体(最大 LIST_LIMIT_RECOMMEND_POOL 件)が /oc 関連ルームの母集団。
 */
class RecommendRankingRepository
{
    private const SELECT_PAGE = "
        oc.id,
        oc.name,
        oc.img_url,
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

    /** 母集団条件・並び順の共通句。各 getRankingBy* で使い回す。 */
    private static function rankingTail(): string
    {
        $minMember = AppConfig::RECOMMEND_MIN_MEMBER_TIER4;
        $day = AppConfig::RANKING_DAY_TABLE_NAME;
        return "
                LEFT JOIN {$day} AS rh24 ON rh24.open_chat_id = oc.id
                LEFT JOIN statistics_ranking_hour AS rh2 ON rh2.open_chat_id = oc.id
            WHERE
                {ENTITY_FILTER}
                AND ((rh24.open_chat_id IS NOT NULL OR rh2.open_chat_id IS NOT NULL) OR oc.member >= {$minMember})
            ORDER BY
                COALESCE(rh24.diff_member, 0) DESC, oc.member DESC, oc.id ASC
            LIMIT :limit";
    }

    private static function selectHead(): string
    {
        $select = self::SELECT_PAGE;
        $day = AppConfig::RANKING_DAY_TABLE_NAME;
        return "SELECT
                {$select},
                CASE WHEN rh24.open_chat_id IS NOT NULL THEN '{$day}' ELSE 'open_chat' END AS table_name,
                COALESCE(rh24.diff_member, 0) AS diff_member_24h";
    }

    /**
     * recommend タグ別。候補は recommend.tag = :tag の部屋。
     */
    function getRankingByTag(string $tag, int $limit): array
    {
        // タグ絞り込みは派生表 ranking 側(WHERE t2.tag = :tag)で済んでいるため外側フィルタは無し。
        $head = self::selectHead();
        $tail = str_replace('{ENTITY_FILTER}', '1 = 1', self::rankingTail());
        return DB::fetchAll(
            "{$head}
            FROM
                (
                    SELECT
                        t2.id,
                        t3.tag AS tag1,
                        t4.tag AS tag2
                    FROM
                        recommend AS t2
                        LEFT JOIN (SELECT * FROM oc_tag GROUP BY id LIMIT 1) AS t3 ON t2.id = t3.id
                        LEFT JOIN (SELECT * FROM oc_tag2 GROUP BY id LIMIT 1) AS t4 ON t2.id = t4.id
                    WHERE
                        t2.tag = :tag
                ) AS ranking
                JOIN open_chat AS oc ON oc.id = ranking.id
            {$tail}",
            compact('tag', 'limit')
        );
    }

    /**
     * LINE カテゴリ別。候補は category 一致の全部屋（tag1=recommend, tag2=oc_tag2 を付与）。
     */
    function getRankingByCategory(int $category, int $limit): array
    {
        $head = self::selectHead();
        $tail = str_replace('{ENTITY_FILTER}', 'oc.category = :category', self::rankingTail());
        return DB::fetchAll(
            "{$head}
            FROM
                open_chat AS oc
                LEFT JOIN (
                    SELECT r.id, MIN(r.tag) AS tag1, MIN(t4.tag) AS tag2
                    FROM recommend AS r
                        LEFT JOIN oc_tag2 AS t4 ON r.id = t4.id
                    GROUP BY r.id
                ) AS ranking ON oc.id = ranking.id
            {$tail}",
            compact('category', 'limit')
        );
    }

    /**
     * 公式ルーム別。$emblem が 0 のときは公式(1)・認証(2)の両方。
     */
    function getRankingByOfficial(int $emblem, int $limit): array
    {
        $entityFilter = $emblem ? "oc.emblem = {$emblem}" : "(oc.emblem = 1 OR oc.emblem = 2)";
        $head = self::selectHead();
        $tail = str_replace('{ENTITY_FILTER}', $entityFilter, self::rankingTail());
        return DB::fetchAll(
            "{$head}
            FROM
                open_chat AS oc
                LEFT JOIN (
                    SELECT r.id, MIN(r.tag) AS tag1, MIN(t4.tag) AS tag2
                    FROM recommend AS r
                        LEFT JOIN oc_tag2 AS t4 ON r.id = t4.id
                    GROUP BY r.id
                ) AS ranking ON oc.id = ranking.id
            {$tail}",
            compact('limit')
        );
    }

    /**
     * @param int[] $idArray
     * @return string[]
     */
    function getRecommendTags(array $idArray): array
    {
        return $this->getTagsFromId($idArray, 'recommend');
    }

    /**
     * @param int[] $idArray
     * @return string[]
     */
    function getOcTags(array $idArray): array
    {
        return $this->getTagsFromId($idArray, 'oc_tag');
    }

    /**
     * @param int[] $idArray
     * @return string[]
     */
    private function getTagsFromId(array $idArray, string $table): array
    {
        $ids = implode(",", $idArray) ?: 0;
        return DB::fetchAll(
            "SELECT tag FROM {$table} WHERE id IN ({$ids})",
            args: [\PDO::FETCH_COLUMN]
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

        $groupedResults = [];
        foreach ($results as $row) {
            $key = $row['category'] ?? 0;
            if (!isset($groupedResults[$key])) {
                $groupedResults[$key] = [];
            }
            $groupedResults[$key][] = [...$row, ...$this->getTagDiffMember($row['tag'])];
        }

        foreach ($groupedResults as &$row) {
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

    /**
     * 関連タグ集計用: recommend(1番手タグ) と oc_tag / oc_tag2(2・3番手にマッチしたタグ) の共起ペア。
     *
     * @return array{tag:string, related:string, cnt:int|string}[]
     */
    function getRelatedTagPairs(): array
    {
        $query =
            'SELECT a.tag, b.tag AS related, COUNT(*) AS cnt
            FROM recommend AS a
                JOIN oc_tag AS b ON b.id = a.id AND b.tag <> a.tag
            GROUP BY a.tag, b.tag
            UNION ALL
            SELECT a.tag, b.tag AS related, COUNT(*) AS cnt
            FROM recommend AS a
                JOIN oc_tag2 AS b ON b.id = a.id AND b.tag <> a.tag
            GROUP BY a.tag, b.tag';

        return DB::fetchAll($query);
    }
}

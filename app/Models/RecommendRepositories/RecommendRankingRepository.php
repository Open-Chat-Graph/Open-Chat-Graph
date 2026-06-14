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
    // RecommendRowFormat::slim() が実際に読む列だけを取得する（rows の唯一の消費者は build()→slim）。
    private const SELECT_PAGE = "
        oc.id,
        oc.name,
        oc.img_url,
        oc.member,
        oc.description,
        oc.emblem,
        oc.api_created_at,
        oc.join_method_type
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
        // 候補は recommend.tag = :tag の部屋（ENTITY_FILTER でタグ条件を注入）。
        $head = self::selectHead();
        $tail = str_replace('{ENTITY_FILTER}', 'ranking.tag = :tag', self::rankingTail());
        return DB::fetchAll(
            "{$head}
            FROM
                recommend AS ranking
                JOIN open_chat AS oc ON oc.id = ranking.id
            {$tail}",
            compact('tag', 'limit')
        );
    }

    /**
     * 複数タグのおすすめランキングを1クエリでまとめて取得する（毎時バッチの .dat 一括生成用）。
     *
     * 並び順・母集団条件は getRankingByTag と完全一致。違いは「タグごとに別クエリ」を
     * ウィンドウ関数 ROW_NUMBER() OVER (PARTITION BY tag ...) に置き換え、タグ N 件ぶんを
     * 1回の SELECT で取り切る点。これで per-tag の N+1（重い JOIN をタグ数ぶん直列）を解消する。
     *
     * tag1/tag2 は呼び出し側（RecommendRowFormat::slim）が使わない死に列だったため取得しない。
     * 行は tag 昇順 → ランキング順で返るので、呼び出し側は tag でグルーピングするだけでよい。
     *
     * @param string[] $tags 取得するタグ名（チャンク）
     * @param int $limit タグごとの最大件数（LIST_LIMIT_RECOMMEND_POOL）
     * @return array<string, array<int, array<string,mixed>>> tag => ランキング順の生行
     */
    function getRankingByTagsBulk(array $tags, int $limit): array
    {
        $tags = array_values($tags);
        if (!$tags) {
            return [];
        }

        $minMember = AppConfig::RECOMMEND_MIN_MEMBER_TIER4;
        $day = AppConfig::RANKING_DAY_TABLE_NAME;
        $limit = max(1, (int)$limit);

        $placeholders = [];
        $params = [];
        foreach ($tags as $i => $tag) {
            $placeholders[] = ":t{$i}";
            $params["t{$i}"] = $tag;
        }
        $in = implode(',', $placeholders);

        $rows = DB::fetchAll(
            "SELECT
                sub.id, sub.name, sub.img_url, sub.member, sub.emblem,
                sub.api_created_at, sub.join_method_type, sub.description,
                sub.table_name, sub.diff_member_24h, sub._bulk_tag
            FROM (
                SELECT
                    oc.id, oc.name, oc.img_url, oc.member, oc.emblem,
                    oc.api_created_at, oc.join_method_type, oc.description,
                    r.tag AS _bulk_tag,
                    CASE WHEN rh24.open_chat_id IS NOT NULL THEN '{$day}' ELSE 'open_chat' END AS table_name,
                    COALESCE(rh24.diff_member, 0) AS diff_member_24h,
                    ROW_NUMBER() OVER (
                        PARTITION BY r.tag
                        ORDER BY COALESCE(rh24.diff_member, 0) DESC, oc.member DESC, oc.id ASC
                    ) AS _rn
                FROM
                    recommend AS r
                    JOIN open_chat AS oc ON oc.id = r.id
                    LEFT JOIN {$day} AS rh24 ON rh24.open_chat_id = oc.id
                    LEFT JOIN statistics_ranking_hour AS rh2 ON rh2.open_chat_id = oc.id
                WHERE
                    r.tag IN ({$in})
                    AND ((rh24.open_chat_id IS NOT NULL OR rh2.open_chat_id IS NOT NULL) OR oc.member >= {$minMember})
            ) AS sub
            WHERE sub._rn <= {$limit}
            ORDER BY sub._bulk_tag, sub._rn",
            $params
        );

        $grouped = [];
        foreach ($rows as $row) {
            $tag = $row['_bulk_tag'];
            unset($row['_bulk_tag']);
            $grouped[$tag][] = $row;
        }

        return $grouped;
    }

    /**
     * LINE カテゴリ別。候補は category 一致の全部屋。
     */
    function getRankingByCategory(int $category, int $limit): array
    {
        $head = self::selectHead();
        $tail = str_replace('{ENTITY_FILTER}', 'oc.category = :category', self::rankingTail());
        return DB::fetchAll(
            "{$head}
            FROM
                open_chat AS oc
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
            {$tail}",
            compact('limit')
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

<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories\Alpha;

use App\Services\Storage\FileStorageInterface;

/**
 * Alpha API用SQLクエリビルダー
 * SQL断片の共通化により、重複を削減
 */
class AlphaQueryBuilder
{
    /**
     * 全カラム（基本 + 統計）のSELECT句
     */
    private function getSelectClause(): string
    {
        $hourlyCronUpdatedAtDatetime = (new \DateTime(file_get_contents(app(FileStorageInterface::class)->getStorageFilePath('hourlyCronUpdatedAtDatetime'))))
            ->format('Y-m-d H:i:s');

        return "
            oc.id,
            oc.name,
            oc.description,
            oc.member,
            oc.img_url,
            oc.emblem,
            oc.join_method_type,
            oc.category,
            oc.created_at,
            oc.api_created_at,
            @is_in_ranking := (SELECT COUNT(*) FROM ocgraph_ranking.member WHERE open_chat_id = oc.id AND time = '{$hourlyCronUpdatedAtDatetime}'),
            CASE
                WHEN @is_in_ranking = 0 THEN NULL
                WHEN h.diff_member IS NULL THEN 0
                ELSE h.diff_member
            END AS hourly_diff,
            CASE
                WHEN @is_in_ranking = 0 THEN NULL
                WHEN h.percent_increase IS NULL THEN 0
                ELSE h.percent_increase
            END AS hourly_percent,
            CASE
                WHEN @is_in_ranking = 0 THEN NULL
                WHEN d.diff_member IS NULL AND TIMESTAMPDIFF(HOUR, oc.created_at, NOW()) >= 24 THEN 0
                ELSE d.diff_member
            END AS daily_diff,
            CASE
                WHEN @is_in_ranking = 0 THEN NULL
                WHEN d.percent_increase IS NULL AND TIMESTAMPDIFF(HOUR, oc.created_at, NOW()) >= 24 THEN 0
                ELSE d.percent_increase
            END AS daily_percent,
            CASE
                WHEN w.diff_member IS NULL AND TIMESTAMPDIFF(DAY, oc.created_at, NOW()) >= 7 THEN 0
                ELSE w.diff_member
            END AS weekly_diff,
            CASE
                WHEN w.percent_increase IS NULL AND TIMESTAMPDIFF(DAY, oc.created_at, NOW()) >= 7 THEN 0
                ELSE w.percent_increase
            END AS weekly_percent,
            CASE
                WHEN @is_in_ranking = 0 THEN 0
                ELSE 1
            END AS is_in_ranking";
    }

    /**
     * 統計ランキングテーブルとのLEFT JOIN
     */
    private function getStatsJoins(): string
    {
        return "
            LEFT JOIN statistics_ranking_hour AS h ON oc.id = h.open_chat_id
            LEFT JOIN statistics_ranking_hour24 AS d ON oc.id = d.open_chat_id
            LEFT JOIN statistics_ranking_week AS w ON oc.id = w.open_chat_id";
    }

    /**
     * 基本検索クエリ構築（キーワードなし、member/created_atソート）
     */
    public function buildSearchQuery(int $category, string $sort, string $order, int $limit, int $offset): array
    {
        $sortMap = [
            'member' => 'oc.member',
            'created_at' => 'oc.api_created_at',
        ];
        $sortColumn = $sortMap[$sort] ?? $sortMap['member'];

        $categoryWhere = $category ? "oc.category = :category" : "1";
        $params = $category ? ['category' => $category] : [];

        // NULL値を下に配置（作成日ソート時はNULLグループ内を人数順に）
        if ($sort === 'created_at') {
            $orderBy = "CASE WHEN oc.api_created_at IS NULL THEN 1 ELSE 0 END ASC, oc.api_created_at {$order}, oc.member {$order}";
        } else {
            $orderBy = "CASE WHEN {$sortColumn} IS NULL THEN 1 ELSE 0 END ASC, {$sortColumn} {$order}";
        }

        $sql = "
            SELECT {$this->getSelectClause()}
            FROM open_chat AS oc
            {$this->getStatsJoins()}
            WHERE {$categoryWhere}
            ORDER BY {$orderBy}
            LIMIT {$limit} OFFSET {$offset}
        ";

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * キーワード検索クエリ構築
     */
    public function buildKeywordSearchQuery(int $category, array $keywords, string $sort, string $order, int $limit, int $offset): array
    {
        $sortMap = [
            'member' => 'oc.member',
            'created_at' => 'oc.api_created_at',
        ];
        $sortColumn = $sortMap[$sort] ?? $sortMap['member'];

        $categoryWhere = $category ? "oc.category = :category" : "1";
        $params = $category ? ['category' => $category] : [];

        // キーワード条件
        $keywordConditions = [];
        foreach ($keywords as $i => $kw) {
            $keywordConditions[] = "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i})";
            $params["keyword{$i}"] = "%{$kw}%";
        }
        $keywordWhere = implode(' AND ', $keywordConditions);

        // NULL値を下に配置（作成日ソート時はNULLグループ内を人数順に）
        if ($sort === 'created_at') {
            $orderBy = "CASE WHEN oc.api_created_at IS NULL THEN 1 ELSE 0 END ASC, oc.api_created_at {$order}, oc.member {$order}";
        } else {
            $orderBy = "CASE WHEN {$sortColumn} IS NULL THEN 1 ELSE 0 END ASC, {$sortColumn} {$order}";
        }

        $sql = "
            SELECT {$this->getSelectClause()}
            FROM open_chat AS oc
            {$this->getStatsJoins()}
            WHERE {$categoryWhere} AND {$keywordWhere}
            ORDER BY {$orderBy}
            LIMIT {$limit} OFFSET {$offset}
        ";

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * ランキングソートクエリ構築（hourly/daily/weekly）
     */
    public function buildRankingQuery(int $category, string $tableName, string $order, int $limit, int $offset): array
    {
        $categoryWhere = $category ? "oc.category = :category" : "1";
        $params = $category ? ['category' => $category] : [];

        $isWeekly = ($tableName === 'statistics_ranking_week');
        // NULL値を下に配置
        $nullHandling = "CASE WHEN sr.diff_member IS NULL THEN 1 ELSE 0 END ASC";
        $orderBy = $isWeekly
            ? "{$nullHandling}, sr.diff_member {$order}, oc.member DESC"
            : "is_in_ranking DESC, {$nullHandling}, sr.diff_member {$order}, oc.member DESC";

        $sql = "
            SELECT {$this->getSelectClause()}
            FROM open_chat AS oc
            JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id
            {$this->getStatsJoins()}
            WHERE {$categoryWhere}
            ORDER BY {$orderBy}
            LIMIT {$limit} OFFSET {$offset}
        ";

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * キーワード付きランキングソートクエリ
     */
    public function buildRankingKeywordQuery(int $category, array $keywords, string $tableName, string $order, int $limit, int $offset): array
    {
        $categoryWhere = $category ? "oc.category = :category" : "1";
        $params = $category ? ['category' => $category] : [];

        // キーワード条件
        $keywordConditions = [];
        foreach ($keywords as $i => $kw) {
            $keywordConditions[] = "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i})";
            $params["keyword{$i}"] = "%{$kw}%";
        }
        $keywordWhere = implode(' AND ', $keywordConditions);

        $isWeekly = ($tableName === 'statistics_ranking_week');
        // NULL値を下に配置
        $nullHandling = "CASE WHEN sr.diff_member IS NULL THEN 1 ELSE 0 END ASC";
        $orderBy = $isWeekly
            ? "{$nullHandling}, sr.diff_member {$order}, oc.member DESC"
            : "is_in_ranking DESC, {$nullHandling}, sr.diff_member {$order}, oc.member DESC";

        $sql = "
            SELECT {$this->getSelectClause()}
            FROM open_chat AS oc
            JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id
            {$this->getStatsJoins()}
            WHERE {$categoryWhere} AND {$keywordWhere}
            ORDER BY {$orderBy}
            LIMIT {$limit} OFFSET {$offset}
        ";

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * UNION補完クエリ構築（最終ページ用）
     */
    public function buildUnionQuery(int $category, string $tableName, string $order, int $limit, int $offset): array
    {
        $categoryWhere = $category ? "oc.category = :category" : "1";
        $params = $category ? ['category' => $category] : [];

        $isWeekly = ($tableName === 'statistics_ranking_week');
        // ランキングデータのないレコード（NULL値）を下に配置
        $nullHandling = "CASE WHEN is_in_ranking = 0 THEN 1 ELSE 0 END ASC";
        $orderBy = $isWeekly
            ? "{$nullHandling}, priority ASC, sort_value {$order}, member DESC"
            : "is_in_ranking DESC, {$nullHandling}, priority ASC, sort_value {$order}, member DESC";

        $sql = "
            SELECT * FROM (
                SELECT {$this->getSelectClause()},
                    sr.diff_member AS sort_value,
                    1 AS priority
                FROM open_chat AS oc
                JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id
                {$this->getStatsJoins()}
                WHERE {$categoryWhere}

                UNION ALL

                SELECT {$this->getSelectClause()},
                    oc.member AS sort_value,
                    2 AS priority
                FROM open_chat AS oc
                {$this->getStatsJoins()}
                WHERE {$categoryWhere}
                  AND oc.id NOT IN (SELECT open_chat_id FROM {$tableName} WHERE 1)
            ) AS combined
            ORDER BY {$orderBy}
            LIMIT {$limit} OFFSET {$offset}
        ";

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * キーワード付きUNION補完クエリ
     */
    public function buildUnionKeywordQuery(int $category, array $keywords, string $tableName, string $order, int $limit, int $offset): array
    {
        $categoryWhere = $category ? "oc.category = :category" : "1";
        $params = $category ? ['category' => $category] : [];

        // キーワード条件
        $keywordConditions = [];
        foreach ($keywords as $i => $kw) {
            $keywordConditions[] = "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i})";
            $params["keyword{$i}"] = "%{$kw}%";
        }
        $keywordWhere = implode(' AND ', $keywordConditions);

        $isWeekly = ($tableName === 'statistics_ranking_week');
        // ランキングデータのないレコード（NULL値）を下に配置
        $nullHandling = "CASE WHEN is_in_ranking = 0 THEN 1 ELSE 0 END ASC";
        $orderBy = $isWeekly
            ? "{$nullHandling}, priority ASC, sort_value {$order}, member DESC"
            : "is_in_ranking DESC, {$nullHandling}, priority ASC, sort_value {$order}, member DESC";

        $sql = "
            SELECT * FROM (
                SELECT {$this->getSelectClause()},
                    sr.diff_member AS sort_value,
                    1 AS priority
                FROM open_chat AS oc
                JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id
                {$this->getStatsJoins()}
                WHERE {$categoryWhere} AND {$keywordWhere}

                UNION ALL

                SELECT {$this->getSelectClause()},
                    oc.member AS sort_value,
                    2 AS priority
                FROM open_chat AS oc
                {$this->getStatsJoins()}
                WHERE {$categoryWhere} AND {$keywordWhere}
                  AND oc.id NOT IN (SELECT open_chat_id FROM {$tableName} WHERE 1)
            ) AS combined
            ORDER BY {$orderBy}
            LIMIT {$limit} OFFSET {$offset}
        ";

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * ID一括取得クエリ（batchStats用）
     */
    public function buildBatchQuery(array $ids): array
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "
            SELECT {$this->getSelectClause()}
            FROM open_chat AS oc
            {$this->getStatsJoins()}
            WHERE oc.id IN ({$placeholders})
            ORDER BY FIELD(oc.id, {$placeholders})
        ";

        // パラメータを2回（IN句とORDER BY FIELD用）、1始まりのインデックスに変換
        $allIds = array_merge($ids, $ids);
        $params = array_combine(range(1, count($allIds)), $allIds);

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * 件数取得クエリ
     */
    public function buildCountQuery(int $category, ?array $keywords = null): array
    {
        $categoryWhere = $category ? "oc.category = :category" : "1";
        $params = $category ? ['category' => $category] : [];

        $keywordWhere = '1';
        if ($keywords) {
            $keywordConditions = [];
            foreach ($keywords as $i => $kw) {
                $keywordConditions[] = "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i})";
                $params["keyword{$i}"] = "%{$kw}%";
            }
            $keywordWhere = implode(' AND ', $keywordConditions);
        }

        $sql = "
            SELECT COUNT(*) as count
            FROM open_chat AS oc
            WHERE {$categoryWhere} AND {$keywordWhere}
        ";

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * ランキング件数取得クエリ
     */
    public function buildRankingCountQuery(int $category, string $tableName, ?array $keywords = null): array
    {
        $categoryWhere = $category ? "oc.category = :category" : "1";
        $params = $category ? ['category' => $category] : [];

        $keywordWhere = '1';
        if ($keywords) {
            $keywordConditions = [];
            foreach ($keywords as $i => $kw) {
                $keywordConditions[] = "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i})";
                $params["keyword{$i}"] = "%{$kw}%";
            }
            $keywordWhere = implode(' AND ', $keywordConditions);
        }

        $sql = "
            SELECT COUNT(*) as count
            FROM open_chat AS oc
            JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id
            WHERE {$categoryWhere} AND {$keywordWhere}
        ";

        return ['sql' => $sql, 'params' => $params];
    }
}

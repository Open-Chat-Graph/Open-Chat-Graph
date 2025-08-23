<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories;

use App\Models\Repositories\DB;

class OpenChatStatsRankingApiRepository
{
    function findHourlyStatsRanking(OpenChatApiArgs $args): array
    {
        return array_map(
            fn($oc) => new OpenChatListDto($oc),
            $this->getStatsRanking('statistics_ranking_hour', $args)
        );
    }

    function findDailyStatsRanking(OpenChatApiArgs $args): array
    {
        return array_map(
            fn($oc) => new OpenChatListDto($oc),
            $this->getStatsRanking('statistics_ranking_hour24', $args)
        );
    }

    function findWeeklyStatsRanking(OpenChatApiArgs $args): array
    {
        return array_map(
            fn($oc) => new OpenChatListDto($oc),
            $this->getStatsRanking('statistics_ranking_week', $args)
        );
    }

    private function getStatsRanking(string $tableName, OpenChatApiArgs $args): array
    {
        $sort = [
            'rank' => 'sr.id',
            'increase' => 'sr.diff_member',
            'rate' => 'sr.percent_increase',
        ];

        $sortColumn = $sort[$args->sort] ?? $sort['rate'];

        $params = [
            'offset' => $args->page * $args->limit,
            'limit' => $args->limit,
        ];

        $query = fn($category) => fn($where) =>
        "SELECT
            oc.id,
            oc.name,
            oc.description,
            oc.member,
            oc.local_img_url AS img_url,
            oc.emblem,
            oc.join_method_type,
            oc.category,
            sr.diff_member,
            sr.percent_increase
        FROM
            open_chat AS oc
            JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id
        {$where} {$category}
        ORDER BY
            {$sortColumn} {$args->order}
        LIMIT :offset, :limit";

        $countQuery = fn($category) => fn($where) =>
        "SELECT
            count(*) as count
        FROM
            open_chat AS oc
            JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id
        {$where} {$category}";

        $categoryStatement = $args->category ? "category = {$args->category}" : "1";

        // 検索が選択されていない場合
        if (!$args->sub_category && !$args->keyword && !$args->tag && !$args->badge) {
            $result = DB::fetchAll(
                $query($categoryStatement)('WHERE'),
                $params
            );

            if (!$result || $args->page !== 0) {
                return $result;
            }

            // 1ページ目の場合は件数を含める
            $result[0]['totalCount'] = DB::fetchColumn($countQuery($categoryStatement)('WHERE'));
            return $result;
        }

        // サブカテゴリー選択時
        if ($args->sub_category) {
            $result = DB::executeLikeSearchQuery(
                $query("AND " . $categoryStatement),
                fn($i) => "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i})",
                $args->sub_category,
                $params
            );

            if (!$result || $args->page !== 0) {
                return $result;
            }

            $count = DB::executeLikeSearchQuery(
                $countQuery("AND " . $categoryStatement),
                fn($i) => "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i})",
                $args->sub_category
            );

            $result[0]['totalCount'] = $count[0]['count'];

            return $result;
        }

        // スペシャル
        if ($args->badge) {
            $param2 = [];
            if ($args->badge < 3) {
                $categoryArg = $categoryStatement . " AND oc.emblem = :emblem";
                $param2 = ['emblem' => $args->badge];
                $result = DB::fetchAll(
                    $query($categoryArg)('WHERE'),
                    [...$params, ...$param2]
                );
            } else {
                $categoryArg = $categoryStatement . " AND oc.emblem > 0";
                $result = DB::fetchAll(
                    $query($categoryArg)('WHERE'),
                    [...$params]
                );
            }


            if (!$result || $args->page !== 0) {
                return $result;
            }

            // 1ページ目の場合は件数を含める
            $result[0]['totalCount'] = DB::fetchColumn($countQuery($categoryArg)('WHERE'), $param2);
            return $result;
        }

        // tag検索時
        if ($args->tag) {
            $categoryArg = $categoryStatement . " AND r.tag = :tag";
            $whereArg = 'JOIN recommend AS r ON oc.id = r.id WHERE';

            $result = DB::fetchAll(
                $query($categoryArg)($whereArg),
                [...$params, 'tag' => $args->tag]
            );

            if (!$result || $args->page !== 0) {
                return $result;
            }

            // 1ページ目の場合は件数を含める
            $result[0]['totalCount'] = DB::fetchColumn($countQuery($categoryArg)($whereArg), ['tag' => $args->tag]);
            return $result;
        }

        // キーワード検索時
        return $this->getStatsRankingWithKeywordPriority($tableName, $args, $sortColumn, $categoryStatement);
    }

    function findStatsAll(OpenChatApiArgs $args): array
    {
        $sort = [
            'member' => 'oc.member',
            'created_at' => 'oc.api_created_at',
        ];

        $where = [
            'member' => '',
            'created_at' => " AND oc.api_created_at IS NOT NULL AND oc.api_created_at != ''",
        ];

        $sortColumn = $sort[$args->sort] ?? $sort['member'];
        $whereClause = $where[$args->sort] ?? $where['member'];

        $params = [
            'offset' => $args->page * $args->limit,
            'limit' => $args->limit,
        ];

        $query = fn($category) => fn($where) =>
        "SELECT
            oc.id,
            oc.name,
            oc.description,
            oc.member,
            oc.local_img_url AS img_url,
            oc.emblem,
            oc.join_method_type,
            oc.category,
            oc.api_created_at
        FROM
            open_chat AS oc
        {$where} {$category}
        ORDER BY
            {$sortColumn} {$args->order}
        LIMIT :offset, :limit";

        $countQuery = fn($category) => fn($where) =>
        "SELECT
            count(*) as count
        FROM
            open_chat AS oc
        {$where} {$category}";

        $categoryStatement = $args->category ? "category = {$args->category}" : "1";

        // サブカテゴリーが選択されていない場合
        if (!$args->sub_category && !$args->keyword && !$args->tag && !$args->badge) {
            $result = array_map(
                fn($oc) => new OpenChatListDto($oc),
                DB::fetchAll(
                    $query($categoryStatement . $whereClause)('WHERE'),
                    $params
                )
            );

            if (!$result || $args->page !== 0) {
                return $result;
            }

            // 1ページ目の場合は件数を含める
            $result[0]->totalCount = DB::fetchColumn($countQuery($categoryStatement . $whereClause)('WHERE'));
            return $result;
        }

        // サブカテゴリー選択時
        if ($args->sub_category) {
            $result = array_map(
                fn($oc) => new OpenChatListDto($oc),
                DB::executeLikeSearchQuery(
                    $query("AND " . $categoryStatement . $whereClause),
                    fn($i) => "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i})",
                    $args->sub_category,
                    $params
                )
            );

            if (!$result || $args->page !== 0) {
                return $result;
            }

            $count = DB::executeLikeSearchQuery(
                $countQuery("AND " . $categoryStatement . $whereClause),
                fn($i) => "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i})",
                $args->sub_category
            );

            $result[0]->totalCount = $count[0]['count'];
            return $result;
        }

        // スペシャル
        if ($args->badge) {
            $param2 = [];

            if ($args->badge < 3) {
                $categoryArg = $categoryStatement . $whereClause . " AND oc.emblem = :emblem";
                $param2 = ['emblem' => $args->badge];
                $result = array_map(
                    fn($oc) => new OpenChatListDto($oc),
                    DB::fetchAll(
                        $query($categoryArg)('WHERE'),
                        [...$params, ...$param2]
                    )
                );
            } else {
                $categoryArg = $categoryStatement . $whereClause . " AND oc.emblem > 0";
                $result = array_map(
                    fn($oc) => new OpenChatListDto($oc),
                    DB::fetchAll(
                        $query($categoryArg)('WHERE'),
                        $params
                    )
                );
            }

            if (!$result || $args->page !== 0) {
                return $result;
            }

            // 1ページ目の場合は件数を含める
            $result[0]->totalCount = DB::fetchColumn($countQuery($categoryArg)('WHERE'), $param2);
            return $result;
        }

        // tag検索時
        if ($args->tag) {
            $categoryArg = $categoryStatement . $whereClause . " AND r.tag = :tag";
            $whereArg = 'JOIN recommend AS r ON oc.id = r.id WHERE';

            $result = array_map(
                fn($oc) => new OpenChatListDto($oc),
                DB::fetchAll(
                    $query($categoryArg)($whereArg),
                    [...$params, 'tag' => $args->tag]
                )
            );

            if (!$result || $args->page !== 0) {
                return $result;
            }

            // 1ページ目の場合は件数を含める
            $result[0]->totalCount = DB::fetchColumn($countQuery($categoryArg)($whereArg), ['tag' => $args->tag]);
            return $result;
        }

        // キーワード検索時
        return $this->getStatsAllWithKeywordPriority($args, $sortColumn, $categoryStatement, $whereClause);
    }

    private function getStatsRankingWithKeywordPriority(string $tableName, OpenChatApiArgs $args, string $sortColumn, $categoryStatement): array
    {
        $params = [
            'offset' => $args->page * $args->limit,
            'limit' => $args->limit,
        ];

        // name一致を優先するUNIONクエリ  
        $sortColumnAlias = str_replace('sr.', '', $sortColumn); // エイリアス調整
        $query = "
        SELECT * FROM (
            SELECT
                oc.id,
                oc.name,
                oc.description,
                oc.member,
                oc.local_img_url AS img_url,
                oc.emblem,
                oc.join_method_type,
                oc.category,
                sr.diff_member,
                sr.percent_increase,
                1 as priority
            FROM
                open_chat AS oc
                JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id
            WHERE
                {$categoryStatement}
                AND (%s)
            
            UNION
            
            SELECT
                oc.id,
                oc.name,
                oc.description,
                oc.member,
                oc.local_img_url AS img_url,
                oc.emblem,
                oc.join_method_type,
                oc.category,
                sr.diff_member,
                sr.percent_increase,
                2 as priority
            FROM
                open_chat AS oc
                JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id
            WHERE
                {$categoryStatement}
                AND NOT (%s)
                AND (%s)
        ) AS combined
        ORDER BY
            priority ASC, {$sortColumnAlias} {$args->order}
        LIMIT %d, %d";

        // カウント用クエリ
        $countQuery = "
        SELECT count(*) as count
        FROM
            open_chat AS oc
            JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id
        WHERE
            {$categoryStatement}
            AND (%s)";

        $result = $this->executeKeywordSearchWithPriority(
            $query,
            $args->keyword,
            $params
        );

        if (!$result || $args->page !== 0) {
            return $result;
        }

        $count = $this->executeKeywordCountQuery($countQuery, $args->keyword);

        $result[0]['totalCount'] = $count[0]['count'];
        return $result;
    }

    private function getStatsAllWithKeywordPriority(OpenChatApiArgs $args, string $sortColumn, $categoryStatement, string $whereClause): array
    {
        $params = [
            'offset' => $args->page * $args->limit,
            'limit' => $args->limit,
        ];

        // name一致を優先するUNIONクエリ
        $sortColumnAlias = str_replace('oc.', '', $sortColumn); // エイリアス調整
        $query = "
        SELECT * FROM (
            SELECT
                oc.id,
                oc.name,
                oc.description,
                oc.member,
                oc.local_img_url AS img_url,
                oc.emblem,
                oc.join_method_type,
                oc.category,
                oc.api_created_at,
                1 as priority
            FROM
                open_chat AS oc
            WHERE
                {$categoryStatement}{$whereClause}
                AND (%s)
            
            UNION
            
            SELECT
                oc.id,
                oc.name,
                oc.description,
                oc.member,
                oc.local_img_url AS img_url,
                oc.emblem,
                oc.join_method_type,
                oc.category,
                oc.api_created_at,
                2 as priority
            FROM
                open_chat AS oc
            WHERE
                {$categoryStatement}{$whereClause}
                AND NOT (%s)
                AND (%s)
        ) AS combined
        ORDER BY
            priority ASC, {$sortColumnAlias} {$args->order}
        LIMIT %d, %d";

        // カウント用クエリ
        $countQuery = "
        SELECT count(*) as count
        FROM
            open_chat AS oc
        WHERE
            {$categoryStatement}{$whereClause}
            AND (%s)";

        $result = array_map(
            fn($oc) => new OpenChatListDto($oc),
            $this->executeKeywordSearchWithPriority(
                $query,
                $args->keyword,
                $params
            )
        );

        if (!$result || $args->page !== 0) {
            return $result;
        }

        $count = $this->executeKeywordCountQuery($countQuery, $args->keyword);

        $result[0]->totalCount = $count[0]['count'];
        return $result;
    }

    private function executeKeywordSearchWithPriority(string $query, string $keyword, array $params): array
    {
        // キーワードを分割（全角スペースを半角スペースに変換してから分割）
        $normalizedKeyword = str_replace('　', ' ', $keyword);
        $keywords = array_filter(explode(' ', $normalizedKeyword), fn($k) => !empty(trim($k)));
        if (empty($keywords)) {
            return [];
        }

        // プレースホルダーを準備
        $nameConditions = [];
        $descConditions = [];
        $allConditions = [];
        $searchParams = $params;
        
        foreach ($keywords as $i => $kw) {
            $nameConditions[] = "oc.name LIKE :keyword{$i}";
            $descConditions[] = "oc.description LIKE :keyword{$i}";
            $allConditions[] = "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i} OR oc.id LIKE :keyword{$i})";
            $searchParams["keyword{$i}"] = "%{$kw}%";
        }

        $nameCondition = implode(' AND ', $nameConditions);
        $descCondition = implode(' AND ', $descConditions);

        // クエリにプレースホルダーを置換 (LIMITの値も含む)
        $finalQuery = sprintf(
            $query,
            $nameCondition,      // name一致部分
            $nameCondition,      // NOT条件のname一致部分
            $descCondition,      // description一致部分
            (int)$params['offset'],  // offset
            (int)$params['limit']    // limit
        );

        // LIMITパラメータをsearchParamsから除外
        unset($searchParams['offset'], $searchParams['limit']);

        DB::connect();
        $stmt = DB::$pdo->prepare($finalQuery);
        $stmt->execute($searchParams);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function executeKeywordCountQuery(string $countQuery, string $keyword): array
    {
        // キーワードを分割（全角スペースを半角スペースに変換してから分割）
        $normalizedKeyword = str_replace('　', ' ', $keyword);
        $keywords = array_filter(explode(' ', $normalizedKeyword), fn($k) => !empty(trim($k)));
        if (empty($keywords)) {
            return [['count' => 0]];
        }

        $allConditions = [];
        $searchParams = [];
        
        foreach ($keywords as $i => $kw) {
            $allConditions[] = "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i} OR oc.id LIKE :keyword{$i})";
            $searchParams["keyword{$i}"] = "%{$kw}%";
        }

        $allCondition = implode(' AND ', $allConditions);
        $finalQuery = sprintf($countQuery, $allCondition);

        DB::connect();
        $stmt = DB::$pdo->prepare($finalQuery);
        $stmt->execute($searchParams);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

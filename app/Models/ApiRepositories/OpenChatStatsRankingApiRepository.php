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
        LIMIT
            :offset, :limit";

        $countQuery = fn($category) => fn($where) =>
        "SELECT
            count(*) as count
        FROM
            open_chat AS oc
            JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id
        {$where} {$category}";

        $categoryStatement = $args->category ? "category = {$args->category}" : 1;

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

        // キーワード検索時 - nameを優先、その後description、最後にidでのマッチ
        $nameWhereClause = '';
        $descWhereClause = '';
        $idWhereClause = '';
        
        $result = DB::executeLikeSearchQuery(
            function($whereClause) use ($tableName, $categoryStatement, $sortColumn, $args, &$nameWhereClause, &$descWhereClause, &$idWhereClause) {
                $nameWhereClause = str_replace(['OR oc.description LIKE', 'OR oc.id LIKE'], ['', ''], $whereClause);
                $descWhereClause = str_replace(['(oc.name LIKE', 'OR oc.id LIKE'], ['(oc.description LIKE', ''], $whereClause);
                $idWhereClause = str_replace(['(oc.name LIKE', 'OR oc.description LIKE'], ['(oc.id LIKE', ''], $whereClause);
                
                return "SELECT * FROM (
                    (SELECT
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
                    WHERE {$categoryStatement} AND {$nameWhereClause}
                    ORDER BY
                        {$sortColumn} {$args->order})
                    UNION
                    (SELECT
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
                    WHERE {$categoryStatement} AND {$descWhereClause} AND oc.id NOT IN (
                        SELECT oc.id FROM open_chat AS oc 
                        JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id 
                        WHERE {$categoryStatement} AND {$nameWhereClause}
                    )
                    ORDER BY
                        {$sortColumn} {$args->order})
                    UNION
                    (SELECT
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
                    WHERE {$categoryStatement} AND {$idWhereClause} AND oc.id NOT IN (
                        SELECT oc.id FROM open_chat AS oc 
                        JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id 
                        WHERE {$categoryStatement} AND ({$nameWhereClause} OR {$descWhereClause})
                    )
                    ORDER BY
                        {$sortColumn} {$args->order})
                ) AS union_result
                LIMIT :offset, :limit";
            },
            fn($i) => "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i} OR oc.id LIKE :keyword{$i})",
            $args->keyword,
            $params
        );

        if (!$result || $args->page !== 0) {
            return $result;
        }

        $count = DB::executeLikeSearchQuery(
            function($whereClause) use ($tableName, $categoryStatement, $nameWhereClause, $descWhereClause, $idWhereClause) {
                return "SELECT count(*) as count FROM (
                    SELECT oc.id
                    FROM open_chat AS oc
                        JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id
                    WHERE {$categoryStatement} AND {$nameWhereClause}
                    UNION
                    SELECT oc.id
                    FROM open_chat AS oc
                        JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id
                    WHERE {$categoryStatement} AND {$descWhereClause} AND oc.id NOT IN (
                        SELECT oc.id FROM open_chat AS oc 
                        JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id 
                        WHERE {$categoryStatement} AND {$nameWhereClause}
                    )
                    UNION
                    SELECT oc.id
                    FROM open_chat AS oc
                        JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id
                    WHERE {$categoryStatement} AND {$idWhereClause} AND oc.id NOT IN (
                        SELECT oc.id FROM open_chat AS oc 
                        JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id 
                        WHERE {$categoryStatement} AND ({$nameWhereClause} OR {$descWhereClause})
                    )
                ) AS union_count";
            },
            fn($i) => "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i} OR oc.id LIKE :keyword{$i})",
            $args->keyword
        );

        $result[0]['totalCount'] = $count[0]['count'];
        return $result;
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
        LIMIT
            :offset, :limit";

        $countQuery = fn($category) => fn($where) =>
        "SELECT
            count(*) as count
        FROM
            open_chat AS oc
        {$where} {$category}";

        $categoryStatement = $args->category ? "category = {$args->category}" : 1;

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

        // キーワード検索時 - nameを優先、その後description、最後にidでのマッチ
        $nameWhereClause2 = '';
        $descWhereClause2 = '';
        $idWhereClause2 = '';
        
        $result = array_map(
            fn($oc) => new OpenChatListDto($oc),
            DB::executeLikeSearchQuery(
                function($keywordWhere) use ($categoryStatement, $whereClause, $sortColumn, $args, &$nameWhereClause2, &$descWhereClause2, &$idWhereClause2) {
                    $nameWhereClause2 = str_replace(['OR oc.description LIKE', 'OR oc.id LIKE'], ['', ''], $keywordWhere);
                    $descWhereClause2 = str_replace(['(oc.name LIKE', 'OR oc.id LIKE'], ['(oc.description LIKE', ''], $keywordWhere);
                    $idWhereClause2 = str_replace(['(oc.name LIKE', 'OR oc.description LIKE'], ['(oc.id LIKE', ''], $keywordWhere);
                    
                    return "SELECT * FROM (
                        (SELECT
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
                        WHERE {$categoryStatement} {$whereClause} AND {$nameWhereClause2}
                        ORDER BY
                            {$sortColumn} {$args->order})
                        UNION
                        (SELECT
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
                        WHERE {$categoryStatement} {$whereClause} AND {$descWhereClause2} AND oc.id NOT IN (
                            SELECT oc.id FROM open_chat AS oc 
                            WHERE {$categoryStatement} {$whereClause} AND {$nameWhereClause2}
                        )
                        ORDER BY
                            {$sortColumn} {$args->order})
                        UNION
                        (SELECT
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
                        WHERE {$categoryStatement} {$whereClause} AND {$idWhereClause2} AND oc.id NOT IN (
                            SELECT oc.id FROM open_chat AS oc 
                            WHERE {$categoryStatement} {$whereClause} AND ({$nameWhereClause2} OR {$descWhereClause2})
                        )
                        ORDER BY
                            {$sortColumn} {$args->order})
                    ) AS union_result
                    LIMIT :offset, :limit";
                },
                fn($i) => "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i} OR oc.id LIKE :keyword{$i})",
                $args->keyword,
                $params
            )
        );

        if (!$result || $args->page !== 0) {
            return $result;
        }

        $count = DB::executeLikeSearchQuery(
            function($keywordWhere) use ($categoryStatement, $whereClause, $nameWhereClause2, $descWhereClause2, $idWhereClause2) {
                return "SELECT count(*) as count FROM (
                    SELECT oc.id
                    FROM open_chat AS oc
                    WHERE {$categoryStatement} {$whereClause} AND {$nameWhereClause2}
                    UNION
                    SELECT oc.id
                    FROM open_chat AS oc
                    WHERE {$categoryStatement} {$whereClause} AND {$descWhereClause2} AND oc.id NOT IN (
                        SELECT oc.id FROM open_chat AS oc 
                        WHERE {$categoryStatement} {$whereClause} AND {$nameWhereClause2}
                    )
                    UNION
                    SELECT oc.id
                    FROM open_chat AS oc
                    WHERE {$categoryStatement} {$whereClause} AND {$idWhereClause2} AND oc.id NOT IN (
                        SELECT oc.id FROM open_chat AS oc 
                        WHERE {$categoryStatement} {$whereClause} AND ({$nameWhereClause2} OR {$descWhereClause2})
                    )
                ) AS union_count";
            },
            fn($i) => "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i} OR oc.id LIKE :keyword{$i})",
            $args->keyword
        );

        $result[0]->totalCount = $count[0]['count'];
        return $result;
    }
}

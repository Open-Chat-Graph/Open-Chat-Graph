<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories;

use App\Config\AppConfig;
use App\Models\Repositories\DB;
use App\Services\Recommend\TagDefinition\Ja\RecommendUtility;
use App\Services\Storage\FileStorageInterface;

class OpenChatStatsRankingApiRepository
{
    public function __construct(
        private FileStorageInterface $fileStorage,
    ) {}

    /**
     * 絞り込み無しランキング各リスト(hour/hour24/week)の総件数を
     * カテゴリ別に集計して返す（毎時バッチが事前計算して .dat に保存する用）。
     *
     * 戻り値: [tableName => [0 => 全カテゴリ合計, category => 件数, ...]]
     * キー 0 は「カテゴリ未指定(=全件)」を表す。ページネーションの totalCount は
     * open_chat × ランキング表の JOIN を52k〜63k行ぶん数える重い count(*) だが、
     * 値はランキング表と同じく毎時しか変わらないため、ここで1回だけ集計する。
     *
     * @return array<string, array<int, int>>
     */
    public function buildListCountCache(): array
    {
        $cache = [];
        foreach (
            [
                AppConfig::RANKING_HOUR_TABLE_NAME,
                AppConfig::RANKING_DAY_TABLE_NAME,
                AppConfig::RANKING_WEEK_TABLE_NAME,
            ] as $tableName
        ) {
            // category は open_chat 側のみが持つ列。getStatsRanking の
            // `WHERE category = N` / `WHERE 1` と同じ母集合をカテゴリ別に1クエリで数える。
            $rows = DB::fetchAll(
                "SELECT oc.category AS category, COUNT(*) AS cnt
                 FROM open_chat AS oc
                 JOIN {$tableName} AS sr ON oc.id = sr.open_chat_id
                 GROUP BY oc.category"
            );

            $byCategory = [];
            $total = 0;
            foreach ($rows as $row) {
                $cnt = (int)$row['cnt'];
                $total += $cnt;
                // category が NULL の部屋は合計には入るが、特定カテゴリ絞り込みには出ない
                if ($row['category'] !== null) {
                    $byCategory[(int)$row['category']] = $cnt;
                }
            }
            $byCategory[0] = $total;
            $cache[$tableName] = $byCategory;
        }

        return $cache;
    }

    /**
     * 事前計算済みの総件数を返す。キャッシュが無ければ null（呼び出し側がライブ集計にフォールバック）。
     */
    private function cachedListCount(string $tableName, int $category): ?int
    {
        $cache = $this->fileStorage->getSerializedFile('@rankingListCounts');
        if (is_array($cache) && isset($cache[$tableName][$category])) {
            return (int)$cache[$tableName][$category];
        }
        return null;
    }

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

    /**
     * 与えられたルームID群の recommend タグを集約し、頻度の高いテーマ上位を返す。
     * /ranking の回遊シェルフ用（表示中の上位ルームのタグ → /recommend へ送客）。
     * 表示名・スラッグの変換は ThemeDiscoveryService と同一（extractTag + urlencode）。
     *
     * @param int[] $ids 上位ルームの open_chat_id
     * @return array{name:string,slug:string}[]
     */
    function aggregateRecommendTags(array $ids, int $limit): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }

        $named = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $named[] = ":id{$i}";
            $params["id{$i}"] = $id;
        }
        $in = implode(',', $named);

        $rows = DB::fetchAll(
            "SELECT r.tag, COUNT(*) AS cnt
             FROM recommend AS r
             WHERE r.id IN ({$in}) AND TRIM(r.tag) <> ''
             GROUP BY r.tag
             ORDER BY cnt DESC, r.tag ASC
             LIMIT " . (int)$limit,
            $params
        );

        // 空タグ・表示名が空になるタグは出さない（空チップ防止）。
        $items = [];
        foreach ($rows as $row) {
            $tag = (string)$row['tag'];
            $name = RecommendUtility::extractTag($tag);
            if ($tag === '' || trim($name) === '') {
                continue;
            }
            $items[] = ['name' => $name, 'slug' => urlencode($tag)];
        }

        return $items;
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
            oc.img_url,
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

            // 1ページ目の場合は件数を含める。
            // 絞り込み無しの総件数は毎時バッチが事前計算済み（cachedListCount）。
            // 未生成時のみ従来の重い count(*) にフォールバックする。
            $result[0]['totalCount'] = $this->cachedListCount($tableName, $args->category)
                ?? DB::fetchColumn($countQuery($categoryStatement)('WHERE'));
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
            oc.img_url,
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
                oc.img_url,
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
                oc.img_url,
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
                oc.img_url,
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
                oc.img_url,
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

<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories\Alpha;

use App\Models\Repositories\DB;
use App\Services\Alpha\AlphaPagePathNormalizer;

/**
 * Alpha Labs「アクセス数ランキング」「検索流入(SEO)ランキング」専用リポジトリ。
 *
 * 日次バッチ(batch/exec/alpha_ga_sync.php)が alpha_room_access_daily に保存した
 * 部屋別の日次アクセス/検索流入を、直近N日で SUM 集計し open_chat と join して返す。
 *
 * ja(base)専用。データがまだ無い場合は空配列・baseDate/updatedAt は null を返す
 * （creds 未投入でもエンドポイントは 200 で空を返せる）。
 */
class AlphaAccessRankingRepository
{
    /**
     * アクセス数ランキング（rooms タブの唯一の入口）。各行に全指標＋流入キーワードを付ける。
     *
     * 部屋集合は常に「期間内に PV が1件以上ある部屋」（having SUM(a.pageviews) > 0）で固定し、
     * $sort で並べ替え軸だけを切替える:
     *   'pageviews'  … アクセス数(PV)合計降順（既定）
     *   'seo_total'  … SEO合計（直接 search_clicks ＋ 間接 indirect_seo）降順
     *   'jump_clicks'… 入室数（参加リンク押下）合計降順
     * いずれも同じ部屋集合（PV>0）で並べ替えるだけ。返す指標は全部同じ。
     *
     * @return array{data: array<int, array<string, mixed>>, baseDate: ?string, updatedAt: ?string, hasMore: bool}
     */
    public function getAccessRanking(int $category, string $fromDate, string $toDate, string $order, int $limit, int $offset = 0, string $keyword = '', string $sort = 'pageviews'): array
    {
        // 並び替え軸（部屋集合は常に PV>0 で固定）。
        $orderColumn = match ($sort) {
            'seo_total' => 'seo_total',
            'jump_clicks' => 'jump_clicks',
            default => 'pageviews',
        };
        return $this->fetchRanking(
            having: 'SUM(a.pageviews) > 0',
            orderColumn: $orderColumn,
            category: $category,
            fromDate: $fromDate,
            toDate: $toDate,
            order: $order,
            limit: $limit,
            offset: $offset,
            keyword: $keyword,
        );
    }

    /**
     * 検索流入(SEO)ランキング（pages タブ seo 用途のみ。rooms は getAccessRanking に一本化）。
     * search_position は表示回数(impressions)で加重平均する。
     *
     * @return array{data: array<int, array<string, mixed>>, baseDate: ?string, updatedAt: ?string, hasMore: bool}
     */
    public function getSearchRanking(int $category, string $fromDate, string $toDate, string $order, int $limit, int $offset = 0, string $keyword = ''): array
    {
        return $this->fetchRanking(
            having: 'SUM(a.search_clicks) > 0',
            orderColumn: 'search_clicks',
            category: $category,
            fromDate: $fromDate,
            toDate: $toDate,
            order: $order,
            limit: $limit,
            offset: $offset,
            keyword: $keyword,
        );
    }

    /**
     * アクセス/検索 共通のランキング取得。指定期間で各行の全指標を集計し open_chat と join。
     *
     * 各行: pageviews / active_users / search_clicks(直接SEO) / search_impressions /
     * search_position / jump_clicks(入室=参加リンク押下) / jump_clicks_organic(うちSEO経由) /
     * indirect_seo(間接SEO＝本家内SEOページ経由PV・自己参照除く)。
     *
     * 無限スクロール用に limit+1 件取って hasMore を判定し、返却は limit 件に丸める。
     *
     * keyword が '' でないとき oc.name LIKE :kw でさらに絞り込む。
     *
     * @return array{data: array<int, array<string, mixed>>, baseDate: ?string, updatedAt: ?string, hasMore: bool}
     */
    private function fetchRanking(
        string $having,
        string $orderColumn,
        int $category,
        string $fromDate,
        string $toDate,
        string $order,
        int $limit,
        int $offset,
        string $keyword = '',
    ): array {
        DB::connect();

        $baseDate = DB::fetchColumn('SELECT MAX(`date`) FROM alpha_room_access_daily');
        if ($baseDate === false || $baseDate === null) {
            return ['data' => [], 'baseDate' => null, 'updatedAt' => null, 'hasMore' => false];
        }
        $baseDate = (string)$baseDate;

        $orderSql = strtolower($order) === 'asc' ? 'ASC' : 'DESC';
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $params = ['fromDate' => $fromDate, 'toDate' => $toDate];

        $categoryWhere = '';
        if ($category) {
            $categoryWhere = ' AND oc.category = :category';
            $params['category'] = $category;
        }

        $keywordWhere = '';
        if ($keyword !== '') {
            $keywordWhere = ' AND oc.name LIKE :kw';
            $params['kw'] = '%' . $keyword . '%';
        }

        // 間接SEO（本家内SEOページ経由で到達したPV、自己参照は除く）を部屋ごとに LEFT JOIN。
        // own ドメイン未設定なら 0。
        $host = $this->ownDomainHost();
        if ($host !== '') {
            $indirectSelect = 'MAX(COALESCE(ref.indirect_seo, 0)) AS indirect_seo';
            // SEO合計＝直接GSCクリック＋間接SEO（本家内SEOページ経由PV）。host 未設定時は search_clicks のみ。
            $seoTotalSelect = '(SUM(a.search_clicks) + MAX(COALESCE(ref.indirect_seo, 0))) AS seo_total';
            $indirectJoin = "
                LEFT JOIN (
                    SELECT open_chat_id, SUM(pageviews) AS indirect_seo
                    FROM alpha_room_referrer_daily
                    WHERE `date` BETWEEN :fromDateR AND :toDateR
                      AND (referrer LIKE :own1 OR referrer LIKE :own2)
                      AND referrer NOT LIKE CONCAT('%/oc/', open_chat_id, '%')
                      AND referrer NOT LIKE CONCAT('%/openchat/', open_chat_id, '%')
                    GROUP BY open_chat_id
                ) ref ON ref.open_chat_id = oc.id";
            $params['fromDateR'] = $fromDate;
            $params['toDateR'] = $toDate;
            $params['own1'] = '%//' . $host . '%';
            $params['own2'] = '%//www.' . $host . '%';
        } else {
            $indirectSelect = '0 AS indirect_seo';
            $seoTotalSelect = 'SUM(a.search_clicks) AS seo_total';
            $indirectJoin = '';
        }

        // 次ページ有無の判定用に1件多く取る。
        $fetch = $limit + 1;

        $sql = "
            SELECT
                oc.id,
                oc.name,
                oc.description,
                oc.member,
                oc.img_url,
                oc.emblem,
                oc.category,
                oc.join_method_type,
                oc.created_at,
                oc.api_created_at,
                oc.url,
                SUM(a.pageviews) AS pageviews,
                SUM(a.active_users) AS active_users,
                SUM(a.search_clicks) AS search_clicks,
                SUM(a.search_impressions) AS search_impressions,
                SUM(a.jump_clicks) AS jump_clicks,
                SUM(a.jump_clicks_organic) AS jump_clicks_organic,
                CASE WHEN SUM(a.search_impressions) > 0
                     THEN SUM(a.search_position * a.search_impressions) / SUM(a.search_impressions)
                     ELSE NULL END AS search_position,
                {$indirectSelect},
                {$seoTotalSelect}
            FROM alpha_room_access_daily AS a
            INNER JOIN open_chat AS oc ON oc.id = a.open_chat_id{$indirectJoin}
            WHERE a.`date` BETWEEN :fromDate AND :toDate{$categoryWhere}{$keywordWhere}
            GROUP BY oc.id
            HAVING {$having}
            ORDER BY {$orderColumn} {$orderSql}
            LIMIT {$fetch} OFFSET {$offset}
        ";

        $rows = DB::fetchAll($sql, $params);
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            $rows = array_slice($rows, 0, $limit);
        }

        return [
            'data' => $rows,
            'baseDate' => $baseDate,
            'updatedAt' => $baseDate,
            'hasMore' => $hasMore,
        ];
    }

    /**
     * 指定部屋群の流入検索キーワードを、各部屋ごとに clicks 多い順 上位N語まとめて取得する（N+1回避）。
     *
     * alpha_room_search_query_daily を期間で絞り open_chat_id・query 別に clicks を SUM、
     * 部屋ごとに clicks 降順で上位 $perRoom 語を返す。各部屋カードの「流入キーワード」列挙用。
     *
     * @param array<int, int> $openChatIds
     * @return array<int, array<int, string>> open_chat_id => [query, ...]（clicks 多い順）
     */
    public function getRoomsTopKeywords(array $openChatIds, string $fromDate, string $toDate, int $perRoom = 8): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $openChatIds), static fn($v) => $v > 0)));
        if ($ids === []) {
            return [];
        }
        DB::connect();
        $perRoom = max(1, $perRoom);
        $in = implode(',', $ids); // 整数確定済みなので直接埋め込む

        // 各部屋・各クエリの期間合計 clicks を派生テーブルで集計し、
        // ROW_NUMBER() OVER (PARTITION BY open_chat_id ...) で部屋ごと上位N語に絞る（MariaDB 10.2+ の window 関数使用）。
        $sql = "
            SELECT open_chat_id, query
            FROM (
                SELECT open_chat_id, query, SUM(clicks) AS clicks,
                       ROW_NUMBER() OVER (PARTITION BY open_chat_id ORDER BY SUM(clicks) DESC, query ASC) AS rn
                FROM alpha_room_search_query_daily
                WHERE open_chat_id IN ({$in})
                  AND `date` BETWEEN :fromDate AND :toDate
                GROUP BY open_chat_id, query
                HAVING SUM(clicks) > 0
            ) t
            WHERE t.rn <= {$perRoom}
            ORDER BY t.open_chat_id, t.rn
        ";

        $rows = DB::fetchAll($sql, ['fromDate' => $fromDate, 'toDate' => $toDate]);

        $map = [];
        foreach ($rows as $r) {
            $ocId = (int)$r['open_chat_id'];
            if (!isset($map[$ocId])) {
                $map[$ocId] = [];
            }
            $map[$ocId][] = (string)$r['query'];
        }
        return $map;
    }

    /**
     * 非部屋ページ（トップ '/' / おすすめ '/recommend/{tag}' 等）の指定期間アクセス/検索流入ランキング。
     * 「その他ページ（非オプチャ）」タブ用。limit+1 で hasMore 判定。
     *
     * $orderColumn は 'pageviews'（access用）/ 'search_clicks'（search用）/ 'jump_clicks'（入室数＝近似）。
     *
     * 【入室数(jumpClicks)は近似】ページ単体では参加リンク押下(jump)を計測していないため、
     * 「そのページを参照元(referrer)として到達した部屋の jump_clicks 合計」で代用する。
     * alpha_room_referrer_daily で referrer がそのページ path を含む(LIKE '%path%')部屋・期間を特定し、
     * alpha_room_access_daily の当該部屋・当該期間の jump_clicks を合算する。
     * referrer は URL の一部一致なので過大/重複しうる（複数ページpathが互いに前方部分一致する場合など）。
     * ページ数は少数なので相関サブクエリで都度集計してよい。
     *
     * @return array{data: array<int, array{path:string, label:string, pageviews:int, activeUsers:int, searchClicks:int, searchImpressions:int, searchPosition:?float, jumpClicks:int, jumpClicksOrganic:int}>, baseDate:?string, updatedAt:?string, hasMore:bool}
     */
    public function getPageScopeRanking(string $fromDate, string $toDate, string $order, int $limit, string $orderColumn = 'pageviews', int $offset = 0): array
    {
        DB::connect();

        $baseDate = DB::fetchColumn('SELECT MAX(`date`) FROM alpha_page_access_daily');
        if ($baseDate === false || $baseDate === null) {
            return ['data' => [], 'baseDate' => null, 'updatedAt' => null, 'hasMore' => false];
        }
        $baseDate = (string)$baseDate;

        $orderSql = strtolower($order) === 'asc' ? 'ASC' : 'DESC';
        $orderColumn = in_array($orderColumn, ['search_clicks', 'jump_clicks'], true) ? $orderColumn : 'pageviews';
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        $fetch = $limit + 1;

        // ページ入室数は alpha_page_jump_daily の事前集計を期間 SUM して JOIN する。
        // 旧来の LIKE 相関スキャンを廃止し、path 完全一致の高速 JOIN に変更。
        $sql = "
            SELECT
                p.path,
                MAX(p.label) AS label,
                SUM(p.pageviews) AS pageviews,
                SUM(p.active_users) AS active_users,
                SUM(p.search_clicks) AS search_clicks,
                SUM(p.search_impressions) AS search_impressions,
                CASE WHEN SUM(p.search_impressions) > 0
                     THEN SUM(p.search_position * p.search_impressions) / SUM(p.search_impressions)
                     ELSE NULL END AS search_position,
                COALESCE(SUM(pj.jump_clicks), 0) AS jump_clicks,
                COALESCE(SUM(pj.jump_clicks_organic), 0) AS jump_clicks_organic
            FROM alpha_page_access_daily AS p
            LEFT JOIN alpha_page_jump_daily AS pj
                ON pj.page_path = p.path AND pj.`date` = p.`date`
            WHERE p.`date` BETWEEN :fromDate AND :toDate
            GROUP BY p.path
            ORDER BY {$orderColumn} {$orderSql}
            LIMIT {$fetch} OFFSET {$offset}
        ";

        $rows = DB::fetchAll($sql, [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ]);
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            $rows = array_slice($rows, 0, $limit);
        }

        $data = array_map(static function ($r) {
            return [
                'path' => (string)$r['path'],
                'label' => (string)($r['label'] ?? ''),
                'pageviews' => (int)$r['pageviews'],
                'activeUsers' => (int)$r['active_users'],
                'searchClicks' => (int)$r['search_clicks'],
                'searchImpressions' => (int)$r['search_impressions'],
                'searchPosition' => $r['search_position'] === null ? null : round((float)$r['search_position'], 2),
                'jumpClicks' => (int)($r['jump_clicks'] ?? 0),
                'jumpClicksOrganic' => (int)($r['jump_clicks_organic'] ?? 0),
            ];
        }, $rows);

        return ['data' => $data, 'baseDate' => $baseDate, 'updatedAt' => $baseDate, 'hasMore' => $hasMore];
    }

    /**
     * 上位検索クエリランキング（指定期間の clicks 合計）。無限スクロール用に limit+1 で hasMore 判定。
     *
     * @return array{data: array<int, array{query:string, clicks:int, impressions:int, position:?float}>, baseDate:?string, updatedAt:?string, hasMore:bool}
     */
    public function getSearchQueryRanking(string $fromDate, string $toDate, int $limit, int $offset = 0): array
    {
        DB::connect();

        $baseDate = DB::fetchColumn('SELECT MAX(`date`) FROM alpha_search_query_daily');
        if ($baseDate === false || $baseDate === null) {
            return ['data' => [], 'baseDate' => null, 'updatedAt' => null, 'hasMore' => false];
        }
        $baseDate = (string)$baseDate;
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        $fetch = $limit + 1;

        $sql = "
            SELECT
                query,
                SUM(clicks) AS clicks,
                SUM(impressions) AS impressions,
                CASE WHEN SUM(impressions) > 0
                     THEN SUM(position * impressions) / SUM(impressions)
                     ELSE NULL END AS position
            FROM alpha_search_query_daily
            WHERE `date` BETWEEN :fromDate AND :toDate
            GROUP BY query
            HAVING SUM(clicks) > 0
            ORDER BY clicks DESC
            LIMIT {$fetch} OFFSET {$offset}
        ";

        $rows = DB::fetchAll($sql, ['fromDate' => $fromDate, 'toDate' => $toDate]);
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            $rows = array_slice($rows, 0, $limit);
        }

        $data = array_map(static function ($r) {
            return [
                'query' => (string)$r['query'],
                'clicks' => (int)$r['clicks'],
                'impressions' => (int)$r['impressions'],
                'position' => $r['position'] === null ? null : round((float)$r['position'], 2),
            ];
        }, $rows);

        return ['data' => $data, 'baseDate' => $baseDate, 'updatedAt' => $baseDate, 'hasMore' => $hasMore];
    }

    /**
     * 詳細画面用: 1部屋の直近N日 GA/GSC 指標を集計。
     *
     * pv/uu/clicks/impr/jump = SUM、position = impression加重平均、engagement = 平均。
     * updatedAt はテーブル全体の最新日付（その部屋の最終取得日ではなくバッチ基準日）。
     *
     * @return array{
     *   updatedAt:?string, pageviews:int, activeUsers:int, searchClicks:int,
     *   searchImpressions:int, searchPosition:?float, jumpClicks:int, jumpClicksOrganic:int,
     *   avgEngagementSeconds:?float
     * }
     */
    /**
     * 期間ウィンドウを解決する（詳細メトリクスの期間指定の唯一の入口）。
     *
     * - all=true            → データの全期間（MIN(date)〜MAX(date)）
     * - start/end が Y-m-d   → その範囲（含まれる日付の集計を全部見る。前後関係は自動補正）
     * - それ以外            → 直近 $days 日（既定 30）
     *
     * 基準は alpha_room_access_daily の MIN/MAX。返り値の days は範囲の実日数。
     *
     * @return array{fromDate:string, toDate:string, days:int}
     */
    public function resolveWindow(string $start, string $end, int $days, bool $all): array
    {
        DB::connect();
        $maxRaw = DB::fetchColumn('SELECT MAX(`date`) FROM alpha_room_access_daily');
        $maxDate = ($maxRaw === false || $maxRaw === null) ? date('Y-m-d') : (string)$maxRaw;
        $minRaw = DB::fetchColumn('SELECT MIN(`date`) FROM alpha_room_access_daily');
        $minDate = ($minRaw === false || $minRaw === null) ? $maxDate : (string)$minRaw;

        $isYmd = static fn(string $s): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) === 1;

        if ($all) {
            $from = $minDate;
            $to = $maxDate;
        } elseif ($isYmd($start) && $isYmd($end)) {
            $from = min($start, $end);
            $to = max($start, $end);
        } else {
            $to = $maxDate;
            $from = (new \DateTime($maxDate))->modify('-' . (max(1, $days) - 1) . ' day')->format('Y-m-d');
        }

        $realDays = (new \DateTime($from))->diff(new \DateTime($to))->days + 1;
        return ['fromDate' => $from, 'toDate' => $to, 'days' => $realDays];
    }

    /**
     * 詳細画面用: 1部屋の指定期間メトリクス（GA/GSC 集計）。
     *
     * @return array{updatedAt:?string, pageviews:int, activeUsers:int, searchClicks:int, searchImpressions:int, searchPosition:?float, jumpClicks:int, jumpClicksOrganic:int, avgEngagementSeconds:?float}
     */
    public function getRoomMetrics(int $openChatId, string $fromDate, string $toDate): array
    {
        DB::connect();

        $empty = [
            'updatedAt' => null,
            'pageviews' => 0,
            'activeUsers' => 0,
            'searchClicks' => 0,
            'searchImpressions' => 0,
            'searchPosition' => null,
            'jumpClicks' => 0,
            'jumpClicksOrganic' => 0,
            'avgEngagementSeconds' => null,
        ];

        $baseDate = DB::fetchColumn('SELECT MAX(`date`) FROM alpha_room_access_daily');
        if ($baseDate === false || $baseDate === null) {
            return $empty;
        }
        $baseDate = (string)$baseDate;

        $sql = "
            SELECT
                SUM(pageviews) AS pageviews,
                SUM(active_users) AS active_users,
                SUM(search_clicks) AS search_clicks,
                SUM(search_impressions) AS search_impressions,
                SUM(jump_clicks) AS jump_clicks,
                SUM(jump_clicks_organic) AS jump_clicks_organic,
                CASE WHEN SUM(search_impressions) > 0
                     THEN SUM(search_position * search_impressions) / SUM(search_impressions)
                     ELSE NULL END AS search_position,
                CASE WHEN SUM(active_users) > 0
                     THEN SUM(engagement_seconds * active_users) / SUM(active_users)
                     ELSE NULL END AS engagement_seconds
            FROM alpha_room_access_daily
            WHERE open_chat_id = :id AND `date` BETWEEN :fromDate AND :toDate
        ";

        $row = DB::fetch($sql, ['id' => $openChatId, 'fromDate' => $fromDate, 'toDate' => $toDate]);

        // 期間内にこの部屋の行が無ければ SUM は全て NULL（GROUP無し集計は1行返る）
        if (!$row || $row['pageviews'] === null) {
            $empty['updatedAt'] = $baseDate;
            return $empty;
        }

        return [
            'updatedAt' => $baseDate,
            'pageviews' => (int)($row['pageviews'] ?? 0),
            'activeUsers' => (int)($row['active_users'] ?? 0),
            'searchClicks' => (int)($row['search_clicks'] ?? 0),
            'searchImpressions' => (int)($row['search_impressions'] ?? 0),
            'searchPosition' => $row['search_position'] === null ? null : round((float)$row['search_position'], 2),
            'jumpClicks' => (int)($row['jump_clicks'] ?? 0),
            'jumpClicksOrganic' => (int)($row['jump_clicks_organic'] ?? 0),
            'avgEngagementSeconds' => $row['engagement_seconds'] === null ? null : round((float)$row['engagement_seconds'], 1),
        ];
    }

    /**
     * 本家ドメインのホスト名（SecretsConfig::$gscSiteUrl 由来。ハードコードしない）。
     * 取れなければ ''（その場合 間接SEO は 0 になる）。
     */
    private function ownDomainHost(): string
    {
        $site = trim((string)\App\Config\SecretsConfig::$gscSiteUrl);
        if ($site === '') {
            return '';
        }
        if (str_starts_with($site, 'sc-domain:')) {
            $host = substr($site, strlen('sc-domain:'));
        } else {
            $parsed = parse_url($site, PHP_URL_HOST);
            $host = ($parsed !== null && $parsed !== false) ? $parsed : $site;
        }
        $host = strtolower(trim($host));
        return preg_replace('/^www\./', '', $host) ?? $host;
    }

    /**
     * 間接SEO流入＝本家内のSEOページ（おすすめ/検索結果/ランキング/トップ/他の部屋 等）から
     * 回遊してこの部屋に到達したページビュー数。alpha_room_referrer_daily の本家内リファラ
     * 合計（自分自身=再読込/グラフ操作 は除く）。直接GSCクリック(search_clicks)とは別軸。
     *
     * own ドメインが未設定なら 0。
     */
    public function getRoomIndirectSeo(int $openChatId, string $fromDate, string $toDate): int
    {
        DB::connect();
        $host = $this->ownDomainHost();
        if ($host === '') {
            return 0;
        }

        $sql = "
            SELECT COALESCE(SUM(pageviews), 0) AS indirect
            FROM alpha_room_referrer_daily
            WHERE open_chat_id = :id
              AND `date` BETWEEN :fromDate AND :toDate
              AND (referrer LIKE :own1 OR referrer LIKE :own2)
              AND referrer NOT LIKE :self1
              AND referrer NOT LIKE :self2
        ";
        $val = DB::fetchColumn($sql, [
            'id' => $openChatId,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'own1' => '%//' . $host . '%',
            'own2' => '%//www.' . $host . '%',
            'self1' => '%/oc/' . $openChatId . '%',
            'self2' => '%/openchat/' . $openChatId . '%',
        ]);
        return $val === false || $val === null ? 0 : (int)$val;
    }

    /**
     * 詳細画面用: 1部屋の直近N日 流入検索クエリ（多い順 上位N件）。
     *
     * alpha_room_search_query_daily を query で GROUP し clicks 降順。
     * position は表示回数(impressions)で加重平均。
     *
     * @return array<int, array{query:string, clicks:int, impressions:int, position:?float}>
     */
    public function getRoomSearchQueries(int $openChatId, string $fromDate, string $toDate, int $limit = 20): array
    {
        DB::connect();
        $limit = max(1, $limit);

        $sql = "
            SELECT
                query,
                SUM(clicks) AS clicks,
                SUM(impressions) AS impressions,
                CASE WHEN SUM(impressions) > 0
                     THEN SUM(position * impressions) / SUM(impressions)
                     ELSE NULL END AS position
            FROM alpha_room_search_query_daily
            WHERE open_chat_id = :id AND `date` BETWEEN :fromDate AND :toDate
            GROUP BY query
            ORDER BY clicks DESC
            LIMIT {$limit}
        ";

        $rows = DB::fetchAll($sql, ['id' => $openChatId, 'fromDate' => $fromDate, 'toDate' => $toDate]);

        return array_map(static function ($r) {
            return [
                'query' => (string)$r['query'],
                'clicks' => (int)$r['clicks'],
                'impressions' => (int)$r['impressions'],
                'position' => $r['position'] === null ? null : round((float)$r['position'], 2),
            ];
        }, $rows);
    }

    /**
     * 詳細画面用: 1部屋の直近N日 リファラ元（多い順 上位N件）。
     *
     * alpha_room_referrer_daily を referrer で GROUP し pageviews 降順。
     *
     * @return array<int, array{referrer:string, pageviews:int}>
     */
    public function getRoomReferrers(int $openChatId, string $fromDate, string $toDate, int $limit = 20): array
    {
        DB::connect();
        $limit = max(1, $limit);

        // 自己参照（このページ内＝再読込/グラフ操作）は初期アクセスではないので除外する。
        $sql = "
            SELECT
                referrer,
                SUM(pageviews) AS pageviews
            FROM alpha_room_referrer_daily
            WHERE open_chat_id = :id AND `date` BETWEEN :fromDate AND :toDate
              AND referrer NOT LIKE :self1
              AND referrer NOT LIKE :self2
            GROUP BY referrer
            ORDER BY pageviews DESC
            LIMIT {$limit}
        ";

        $rows = DB::fetchAll($sql, [
            'id' => $openChatId,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'self1' => '%/oc/' . $openChatId . '%',
            'self2' => '%/openchat/' . $openChatId . '%',
        ]);

        return array_map(static function ($r) {
            return [
                'referrer' => (string)$r['referrer'],
                'pageviews' => (int)$r['pageviews'],
            ];
        }, $rows);
    }

    /**
     * 指定日 D の alpha_page_jump_daily を alpha_room_referrer_daily × alpha_room_access_daily
     * から再計算して upsert する。
     *
     * 処理:
     *   1. $date の alpha_room_referrer_daily を全件取得（pageviews 込み）。
     *   2. referrer を page_path に正規化（'/' or '/recommend/{tag}' のみ対象・外部や自己参照は除外）。
     *   3. 部屋の当日 jump_clicks / jump_clicks_organic を「内部ページ流入PV比」で按分（近似）し、
     *      page_path 別に SUM して upsert。referrer 別の jump は存在しないため厳密な帰属は不可能で、
     *      PV 比按分が最善の近似。按分の分母は全 referrer PV（外部 Google 等・(direct)・部屋ページ
     *      referrer 含む）なので、外部由来分は内部ページに帰属させない（ページ合計 ≦ 部屋合計）。
     *
     * PHP 側で正規化する（LIKE のない完全一致 JOIN では正規化できないため）。
     * 当日分のみなので referrer 行数は限定的（実質数千〜数万行）。
     */
    public function rebuildPageJumpDaily(string $date): void
    {
        DB::connect();

        // 当日の referrer 一覧を取得（open_chat_id, referrer, pageviews）
        $refRows = DB::fetchAll(
            "SELECT open_chat_id, referrer, pageviews FROM alpha_room_referrer_daily WHERE `date` = :date",
            ['date' => $date]
        );

        if ($refRows === []) {
            return;
        }

        // referrer → page_path 正規化し、按分用の PV を集計する。
        // 分子: 内部ページ（正規化が非 null）の page_path × 部屋別 PV
        /** @var array<string, array<int, int>> $pathRoomPv page_path => [open_chat_id => pv] */
        $pathRoomPv = [];
        // 分母: 全 referrer 行（外部・(direct)・部屋ページ referrer 含む）の部屋別 PV 合計
        /** @var array<int, int> $roomPvTotal open_chat_id => pv */
        $roomPvTotal = [];
        foreach ($refRows as $r) {
            $ocId = (int)$r['open_chat_id'];
            $pv = (int)$r['pageviews'];
            $roomPvTotal[$ocId] = ($roomPvTotal[$ocId] ?? 0) + $pv;

            $path = AlphaPagePathNormalizer::normalize((string)($r['referrer'] ?? ''))['path'] ?? null;
            if ($path === null) {
                continue;
            }
            $pathRoomPv[$path][$ocId] = ($pathRoomPv[$path][$ocId] ?? 0) + $pv;
        }

        if ($pathRoomPv === []) {
            return;
        }

        // 当日の alpha_room_access_daily を in 句で一括取得して集計
        $allIds = [];
        foreach ($pathRoomPv as $roomPv) {
            foreach (array_keys($roomPv) as $ocId) {
                $allIds[$ocId] = true;
            }
        }
        $in = implode(',', array_map('intval', array_keys($allIds)));

        $accessRows = DB::fetchAll(
            "SELECT open_chat_id, jump_clicks, jump_clicks_organic
             FROM alpha_room_access_daily
             WHERE `date` = :date AND open_chat_id IN ({$in})",
            ['date' => $date]
        );

        // open_chat_id => [jump_clicks, jump_clicks_organic]
        /** @var array<int, array{jump_clicks:int, jump_clicks_organic:int}> $accessMap */
        $accessMap = [];
        foreach ($accessRows as $a) {
            $accessMap[(int)$a['open_chat_id']] = [
                'jump_clicks' => (int)$a['jump_clicks'],
                'jump_clicks_organic' => (int)$a['jump_clicks_organic'],
            ];
        }

        // page_path 別に、部屋の当日 jump を「このページ経由の PV ÷ 全 referrer PV」で按分して SUM
        /** @var array<string, array{jump_clicks:int, jump_clicks_organic:int}> $pageAgg */
        $pageAgg = [];
        foreach ($pathRoomPv as $path => $roomPv) {
            $jc = 0;
            $jco = 0;
            foreach ($roomPv as $ocId => $pv) {
                $total = $roomPvTotal[$ocId] ?? 0;
                if ($total <= 0 || !isset($accessMap[$ocId])) {
                    continue; // ゼロ除算ガード（分母0の部屋の寄与は0）
                }
                $ratio = $pv / $total;
                $jc += (int)round($accessMap[$ocId]['jump_clicks'] * $ratio);
                $jco += (int)round($accessMap[$ocId]['jump_clicks_organic'] * $ratio);
            }
            $pageAgg[$path] = ['jump_clicks' => $jc, 'jump_clicks_organic' => $jco];
        }

        // upsert
        foreach ($pageAgg as $path => $agg) {
            DB::execute(
                "INSERT INTO alpha_page_jump_daily (page_path, `date`, jump_clicks, jump_clicks_organic)
                 VALUES (:path, :date, :jc, :jco)
                 ON DUPLICATE KEY UPDATE
                    jump_clicks = VALUES(jump_clicks),
                    jump_clicks_organic = VALUES(jump_clicks_organic)",
                [
                    'path' => $path,
                    'date' => $date,
                    'jc' => $agg['jump_clicks'],
                    'jco' => $agg['jump_clicks_organic'],
                ]
            );
        }
    }

    /**
     * 指定 id 群の部屋名を取得（参照元「他の部屋（◯◯）」の名前解決用）。
     *
     * @param array<int, int> $ids
     * @return array<int, string> id => name
     */
    public function getRoomNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($v) => $v > 0)));
        if ($ids === []) {
            return [];
        }
        DB::connect();
        $in = implode(',', $ids); // 整数確定済みなので直接埋め込む
        $rows = DB::fetchAll("SELECT id, name FROM open_chat WHERE id IN ({$in})");
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['id']] = (string)$r['name'];
        }
        return $map;
    }
}

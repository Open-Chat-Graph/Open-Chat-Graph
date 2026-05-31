<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories\Alpha;

use App\Models\Repositories\DB;

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
     * アクセス数ランキング（直近N日のページビュー合計）。
     *
     * @param int    $category カテゴリID（0=全カテゴリ）
     * @param int    $days     集計対象の直近日数
     * @param string $order    'desc' / 'asc'
     * @param int    $limit    返却件数の上限
     * @return array{data: array<int, array<string, mixed>>, days: int, baseDate: ?string, updatedAt: ?string}
     */
    public function getAccessRanking(int $category, int $days, string $order, int $limit): array
    {
        return $this->fetchRanking(
            metricSelect: 'SUM(a.pageviews) AS pageviews, SUM(a.active_users) AS active_users',
            having: 'SUM(a.pageviews) > 0',
            orderColumn: 'pageviews',
            category: $category,
            days: $days,
            order: $order,
            limit: $limit,
        );
    }

    /**
     * 検索流入(SEO)ランキング（直近N日の検索クリック合計＋表示回数＋平均掲載順位）。
     *
     * search_position は表示回数(impressions)で加重平均する。
     *
     * @return array{data: array<int, array<string, mixed>>, days: int, baseDate: ?string, updatedAt: ?string}
     */
    public function getSearchRanking(int $category, int $days, string $order, int $limit): array
    {
        return $this->fetchRanking(
            metricSelect: 'SUM(a.search_clicks) AS search_clicks, '
                . 'SUM(a.search_impressions) AS search_impressions, '
                . 'SUM(a.active_users) AS active_users, '
                . 'CASE WHEN SUM(a.search_impressions) > 0 '
                . 'THEN SUM(a.search_position * a.search_impressions) / SUM(a.search_impressions) '
                . 'ELSE NULL END AS search_position',
            having: 'SUM(a.search_clicks) > 0',
            orderColumn: 'search_clicks',
            category: $category,
            days: $days,
            order: $order,
            limit: $limit,
        );
    }

    /**
     * アクセス/検索 共通のランキング取得。
     *
     * @return array{data: array<int, array<string, mixed>>, days: int, baseDate: ?string, updatedAt: ?string}
     */
    private function fetchRanking(
        string $metricSelect,
        string $having,
        string $orderColumn,
        int $category,
        int $days,
        string $order,
        int $limit,
    ): array {
        DB::connect();

        // 基準日（保存済みの最新日付）。テーブルが空ならデータ無しとして空を返す。
        $baseDate = DB::fetchColumn('SELECT MAX(`date`) FROM alpha_room_access_daily');
        if ($baseDate === false || $baseDate === null) {
            return ['data' => [], 'days' => $days, 'baseDate' => null, 'updatedAt' => null];
        }
        $baseDate = (string)$baseDate;

        // 直近N日 = 基準日から (N-1) 日前まで（基準日を含めてN日）
        $fromDate = (new \DateTime($baseDate))->modify('-' . ($days - 1) . ' day')->format('Y-m-d');

        $orderSql = strtolower($order) === 'asc' ? 'ASC' : 'DESC';

        $params = [
            'fromDate' => $fromDate,
            'baseDate' => $baseDate,
        ];

        $categoryWhere = '';
        if ($category) {
            $categoryWhere = ' AND oc.category = :category';
            $params['category'] = $category;
        }

        // LIMIT は整数確定済みなので直接埋め込む（プレースホルダ不可な実装差を避ける）
        $limit = max(1, $limit);

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
                {$metricSelect}
            FROM alpha_room_access_daily AS a
            INNER JOIN open_chat AS oc ON oc.id = a.open_chat_id
            WHERE a.`date` BETWEEN :fromDate AND :baseDate{$categoryWhere}
            GROUP BY oc.id
            HAVING {$having}
            ORDER BY {$orderColumn} {$orderSql}
            LIMIT {$limit}
        ";

        $data = DB::fetchAll($sql, $params);

        // 集計テーブルの更新時刻（基準日の日次データが書かれた最後の瞬間の目安）。
        // 行ごとの更新時刻カラムは持たないので baseDate を更新日として返す。
        return [
            'data' => $data,
            'days' => $days,
            'baseDate' => $baseDate,
            'updatedAt' => $baseDate,
        ];
    }

    /**
     * 非部屋ページ（トップ '/' / おすすめ '/recommend/{tag}'）の直近N日アクセス/検索流入ランキング。
     * access/search 両APIの「pages」別枠で返す（rooms とは別配列）。
     *
     * $orderColumn は 'pageviews'（access用）/ 'search_clicks'（search用）。
     *
     * @return array<int, array{path:string, label:string, pageviews:int, activeUsers:int, searchClicks:int, searchImpressions:int, searchPosition:?float}>
     */
    public function getPageScopeRanking(int $days, string $order, int $limit, string $orderColumn = 'pageviews'): array
    {
        DB::connect();

        $baseDate = DB::fetchColumn('SELECT MAX(`date`) FROM alpha_page_access_daily');
        if ($baseDate === false || $baseDate === null) {
            return [];
        }
        $baseDate = (string)$baseDate;
        $fromDate = (new \DateTime($baseDate))->modify('-' . ($days - 1) . ' day')->format('Y-m-d');

        $orderSql = strtolower($order) === 'asc' ? 'ASC' : 'DESC';
        $orderColumn = $orderColumn === 'search_clicks' ? 'search_clicks' : 'pageviews';
        $limit = max(1, $limit);

        $sql = "
            SELECT
                path,
                MAX(label) AS label,
                SUM(pageviews) AS pageviews,
                SUM(active_users) AS active_users,
                SUM(search_clicks) AS search_clicks,
                SUM(search_impressions) AS search_impressions,
                CASE WHEN SUM(search_impressions) > 0
                     THEN SUM(search_position * search_impressions) / SUM(search_impressions)
                     ELSE NULL END AS search_position
            FROM alpha_page_access_daily
            WHERE `date` BETWEEN :fromDate AND :baseDate
            GROUP BY path
            ORDER BY {$orderColumn} {$orderSql}
            LIMIT {$limit}
        ";

        $rows = DB::fetchAll($sql, ['fromDate' => $fromDate, 'baseDate' => $baseDate]);

        return array_map(static function ($r) {
            return [
                'path' => (string)$r['path'],
                'label' => (string)($r['label'] ?? ''),
                'pageviews' => (int)$r['pageviews'],
                'activeUsers' => (int)$r['active_users'],
                'searchClicks' => (int)$r['search_clicks'],
                'searchImpressions' => (int)$r['search_impressions'],
                'searchPosition' => $r['search_position'] === null ? null : round((float)$r['search_position'], 2),
            ];
        }, $rows);
    }

    /**
     * 上位検索クエリランキング（直近N日の clicks 合計）。
     *
     * @return array{data: array<int, array{query:string, clicks:int, impressions:int, position:?float}>, days:int, baseDate:?string, updatedAt:?string}
     */
    public function getSearchQueryRanking(int $days, int $limit): array
    {
        DB::connect();

        $baseDate = DB::fetchColumn('SELECT MAX(`date`) FROM alpha_search_query_daily');
        if ($baseDate === false || $baseDate === null) {
            return ['data' => [], 'days' => $days, 'baseDate' => null, 'updatedAt' => null];
        }
        $baseDate = (string)$baseDate;
        $fromDate = (new \DateTime($baseDate))->modify('-' . ($days - 1) . ' day')->format('Y-m-d');
        $limit = max(1, $limit);

        $sql = "
            SELECT
                query,
                SUM(clicks) AS clicks,
                SUM(impressions) AS impressions,
                CASE WHEN SUM(impressions) > 0
                     THEN SUM(position * impressions) / SUM(impressions)
                     ELSE NULL END AS position
            FROM alpha_search_query_daily
            WHERE `date` BETWEEN :fromDate AND :baseDate
            GROUP BY query
            HAVING SUM(clicks) > 0
            ORDER BY clicks DESC
            LIMIT {$limit}
        ";

        $rows = DB::fetchAll($sql, ['fromDate' => $fromDate, 'baseDate' => $baseDate]);

        $data = array_map(static function ($r) {
            return [
                'query' => (string)$r['query'],
                'clicks' => (int)$r['clicks'],
                'impressions' => (int)$r['impressions'],
                'position' => $r['position'] === null ? null : round((float)$r['position'], 2),
            ];
        }, $rows);

        return ['data' => $data, 'days' => $days, 'baseDate' => $baseDate, 'updatedAt' => $baseDate];
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

        $sql = "
            SELECT
                referrer,
                SUM(pageviews) AS pageviews
            FROM alpha_room_referrer_daily
            WHERE open_chat_id = :id AND `date` BETWEEN :fromDate AND :toDate
            GROUP BY referrer
            ORDER BY pageviews DESC
            LIMIT {$limit}
        ";

        $rows = DB::fetchAll($sql, ['id' => $openChatId, 'fromDate' => $fromDate, 'toDate' => $toDate]);

        return array_map(static function ($r) {
            return [
                'referrer' => (string)$r['referrer'],
                'pageviews' => (int)$r['pageviews'],
            ];
        }, $rows);
    }
}

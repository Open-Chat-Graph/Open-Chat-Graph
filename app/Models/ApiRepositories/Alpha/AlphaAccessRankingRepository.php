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
            metricSelect: 'SUM(a.pageviews) AS pageviews',
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
}

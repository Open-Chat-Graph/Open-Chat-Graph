<?php

declare(strict_types=1);

namespace App\Services\Alpha;

/**
 * AlphaGaClient が取得した GA4 / GSC API レスポンスの rows を
 * open_chat_id 別 / page 別の集計配列へ変換する純粋ロジック。
 *
 * HTTP・トークン・DB には一切依存しない（入力=APIレスポンスの rows、出力=集計済み配列）。
 * AlphaGaClient（HTTPクライアント）から「レスポンス後の集計・正規化」だけを分離したもの。
 * HTTPリクエスト構築（dimension/metric 定義・ページング制御）はクライアント側に残る。
 *
 * 行形式:
 * - GSC: {keys: string[], clicks: number, impressions: number, position: number}
 * - GA4: {dimensionValues: [{value: string}...], metricValues: [{value: string}...]}
 *
 * ページングされる API に対応するため、accumulate*（ページ毎に rows を中間集計へ加算）と
 * finalize*（中間集計 → 結果配列）の2段構成のものがある。
 * 1リクエストで完結する API は aggregate* の1段で rows → 結果配列まで行う。
 */
final class AlphaGaDataAggregator
{
    /** 本家詳細ページ /oc/{id}（念のため /openchat/{id} も）から数字idを拾う */
    private const PATH_ID_PATTERN = '#/(?:oc|openchat)/(\d+)#';

    /**
     * GSC: page ディメンション行を open_chat_id 別の中間集計へ加算する（fetchSearchAnalytics 用）。
     *
     * @param array<int, array<string, mixed>> $rows GSC searchAnalytics/query の rows
     * @param array<int, array{clicks:int, impressions:int, posWeighted:float}> $acc open_chat_id => 中間集計
     * @return array<int, array{clicks:int, impressions:int, posWeighted:float}>
     */
    public static function accumulateSearchAnalytics(array $rows, array $acc): array
    {
        foreach ($rows as $row) {
            $page = $row['keys'][0] ?? '';
            $id = self::extractOpenChatId((string)$page);
            if ($id === null) {
                continue;
            }
            $clicks = (int)round((float)($row['clicks'] ?? 0));
            $impr = (int)round((float)($row['impressions'] ?? 0));
            $pos = (float)($row['position'] ?? 0);

            if (!isset($acc[$id])) {
                $acc[$id] = ['clicks' => 0, 'impressions' => 0, 'posWeighted' => 0.0];
            }
            $acc[$id]['clicks'] += $clicks;
            $acc[$id]['impressions'] += $impr;
            $acc[$id]['posWeighted'] += $pos * $impr;
        }

        return $acc;
    }

    /**
     * fetchSearchAnalytics 用: 中間集計を結果配列へ変換する。
     * position はインプレッション加重平均で再集計する（複数pathが同一idに畳まれる場合に備える）。
     *
     * @param array<int, array{clicks:int, impressions:int, posWeighted:float}> $acc
     * @return array<int, array{clicks:int, impressions:int, position:?float}>
     */
    public static function finalizeSearchAnalytics(array $acc): array
    {
        $result = [];
        foreach ($acc as $id => $v) {
            $position = $v['impressions'] > 0
                ? round($v['posWeighted'] / $v['impressions'], 2)
                : null;
            $result[$id] = [
                'clicks' => $v['clicks'],
                'impressions' => $v['impressions'],
                'position' => $position,
            ];
        }

        return $result;
    }

    /**
     * GA4: pagePath × (screenPageViews, activeUsers, userEngagementDuration) 行を
     * open_chat_id 別に集計する（fetchRoomMetrics 用）。
     *
     * 平均エンゲージ秒は「Σ(userEngagementDuration) / Σ(activeUsers)」で id 別に算出する
     * （同一idに複数pathが畳まれる場合に備え、合算してから割る）。
     *
     * @param array<int, array<string, mixed>> $rows GA4 runReport の rows
     * @return array<int, array{pageviews:int, activeUsers:int, engagementSeconds:?float}>
     */
    public static function aggregateRoomMetrics(array $rows): array
    {
        // id => [pv, uu, engagementSum]
        $acc = [];
        foreach ($rows as $row) {
            $path = $row['dimensionValues'][0]['value'] ?? '';
            $id = self::extractOpenChatId((string)$path);
            if ($id === null) {
                continue;
            }
            $pv = (int)round((float)($row['metricValues'][0]['value'] ?? 0));
            $uu = (int)round((float)($row['metricValues'][1]['value'] ?? 0));
            $engSum = (float)($row['metricValues'][2]['value'] ?? 0);

            if (!isset($acc[$id])) {
                $acc[$id] = ['pageviews' => 0, 'activeUsers' => 0, 'engagementSum' => 0.0];
            }
            $acc[$id]['pageviews'] += $pv;
            $acc[$id]['activeUsers'] += $uu;
            $acc[$id]['engagementSum'] += $engSum;
        }

        $result = [];
        foreach ($acc as $id => $v) {
            $result[$id] = [
                'pageviews' => $v['pageviews'],
                'activeUsers' => $v['activeUsers'],
                'engagementSeconds' => $v['activeUsers'] > 0
                    ? round($v['engagementSum'] / $v['activeUsers'], 1)
                    : null,
            ];
        }

        return $result;
    }

    /**
     * GA4: pagePath × linkDomain × eventCount 行から、参加リンク押下
     * （/oc/{id}/jump の click & linkDomain=line.me）を open_chat_id 別に合算する（fetchJumpClicks 用）。
     *
     * @param array<int, array<string, mixed>> $rows GA4 runReport の rows
     * @return array<int, int> open_chat_id => jumpClicks
     */
    public static function aggregateJumpClicks(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $path = $row['dimensionValues'][0]['value'] ?? '';
            $linkDomain = (string)($row['dimensionValues'][1]['value'] ?? '');
            // 参加リンク（line.me）以外のクリックは除外
            if (stripos($linkDomain, 'line.me') === false) {
                continue;
            }
            $id = self::extractJumpOpenChatId((string)$path);
            if ($id === null) {
                continue;
            }
            $clicks = (int)round((float)($row['metricValues'][0]['value'] ?? 0));
            $result[$id] = ($result[$id] ?? 0) + $clicks;
        }

        return $result;
    }

    /**
     * GA4: 非部屋ページ（トップ '/' / おすすめ '/recommend/{tag}'）の PV/UU を
     * 正規化済み path 別に集計する（fetchPageMetrics 用）。
     *
     * path の正規化（部屋ページ・外部・tw/th 除外）は AlphaPagePathNormalizer に委ねる。
     *
     * @param array<int, array<string, mixed>> $rows GA4 runReport の rows
     * @return array<string, array{label:string, pageviews:int, activeUsers:int}>
     */
    public static function aggregatePageMetrics(array $rows): array
    {
        $acc = [];
        foreach ($rows as $row) {
            $path = (string)($row['dimensionValues'][0]['value'] ?? '');
            $norm = AlphaPagePathNormalizer::normalize($path);
            if ($norm === null) {
                continue;
            }
            $pv = (int)round((float)($row['metricValues'][0]['value'] ?? 0));
            $uu = (int)round((float)($row['metricValues'][1]['value'] ?? 0));
            if (!isset($acc[$norm['path']])) {
                $acc[$norm['path']] = ['label' => $norm['label'], 'pageviews' => 0, 'activeUsers' => 0];
            }
            $acc[$norm['path']]['pageviews'] += $pv;
            $acc[$norm['path']]['activeUsers'] += $uu;
        }

        return $acc;
    }

    /**
     * GSC: page ディメンション行を非部屋ページの正規化済み path 別の中間集計へ加算する
     * （fetchPageSearchAnalytics 用）。
     *
     * @param array<int, array<string, mixed>> $rows GSC searchAnalytics/query の rows
     * @param array<string, array{clicks:int, impressions:int, posWeighted:float}> $acc path => 中間集計
     * @return array<string, array{clicks:int, impressions:int, posWeighted:float}>
     */
    public static function accumulatePageSearchAnalytics(array $rows, array $acc): array
    {
        foreach ($rows as $row) {
            $page = (string)($row['keys'][0] ?? '');
            $norm = AlphaPagePathNormalizer::normalize($page);
            if ($norm === null) {
                continue;
            }
            $clicks = (int)round((float)($row['clicks'] ?? 0));
            $impr = (int)round((float)($row['impressions'] ?? 0));
            $pos = (float)($row['position'] ?? 0);
            if (!isset($acc[$norm['path']])) {
                $acc[$norm['path']] = ['clicks' => 0, 'impressions' => 0, 'posWeighted' => 0.0];
            }
            $acc[$norm['path']]['clicks'] += $clicks;
            $acc[$norm['path']]['impressions'] += $impr;
            $acc[$norm['path']]['posWeighted'] += $pos * $impr;
        }

        return $acc;
    }

    /**
     * fetchPageSearchAnalytics 用: 中間集計を結果配列へ変換する（position はインプレッション加重平均）。
     *
     * @param array<string, array{clicks:int, impressions:int, posWeighted:float}> $acc
     * @return array<string, array{clicks:int, impressions:int, position:?float}>
     */
    public static function finalizePageSearchAnalytics(array $acc): array
    {
        $result = [];
        foreach ($acc as $path => $v) {
            $result[$path] = [
                'clicks' => $v['clicks'],
                'impressions' => $v['impressions'],
                'position' => $v['impressions'] > 0 ? round($v['posWeighted'] / $v['impressions'], 2) : null,
            ];
        }

        return $result;
    }

    /**
     * GSC: query ディメンション行を上位検索クエリの結果配列へ変換する（fetchTopSearchQueries 用）。
     * query は190字に丸め、空クエリは除外する。
     *
     * @param array<int, array<string, mixed>> $rows GSC searchAnalytics/query の rows
     * @return array<int, array{query:string, clicks:int, impressions:int, position:?float}>
     */
    public static function aggregateTopSearchQueries(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $query = (string)($row['keys'][0] ?? '');
            if ($query === '') {
                continue;
            }
            $clicks = (int)round((float)($row['clicks'] ?? 0));
            $impr = (int)round((float)($row['impressions'] ?? 0));
            $pos = isset($row['position']) ? round((float)$row['position'], 2) : null;
            $result[] = [
                'query' => mb_strlen($query) > 190 ? mb_substr($query, 0, 190) : $query,
                'clicks' => $clicks,
                'impressions' => $impr,
                'position' => $pos,
            ];
        }

        return $result;
    }

    /**
     * GSC: [page, query] ディメンション行を open_chat_id × query 別の中間集計へ加算する
     * （fetchRoomSearchQueries 用）。page を PATH_ID_PATTERN で open_chat_id に畳む。
     *
     * @param array<int, array<string, mixed>> $rows GSC searchAnalytics/query の rows
     * @param array<int, array<string, array{clicks:int, impressions:int, posWeighted:float}>> $acc open_chat_id => query => 中間集計
     * @return array<int, array<string, array{clicks:int, impressions:int, posWeighted:float}>>
     */
    public static function accumulateRoomSearchQueries(array $rows, array $acc): array
    {
        foreach ($rows as $row) {
            $page = (string)($row['keys'][0] ?? '');
            $query = (string)($row['keys'][1] ?? '');
            if ($query === '') {
                continue;
            }
            $id = self::extractOpenChatId($page);
            if ($id === null) {
                continue;
            }
            $query = mb_strlen($query) > 190 ? mb_substr($query, 0, 190) : $query;
            $clicks = (int)round((float)($row['clicks'] ?? 0));
            $impr = (int)round((float)($row['impressions'] ?? 0));
            $pos = (float)($row['position'] ?? 0);

            if (!isset($acc[$id][$query])) {
                $acc[$id][$query] = ['clicks' => 0, 'impressions' => 0, 'posWeighted' => 0.0];
            }
            $acc[$id][$query]['clicks'] += $clicks;
            $acc[$id][$query]['impressions'] += $impr;
            $acc[$id][$query]['posWeighted'] += $pos * $impr;
        }

        return $acc;
    }

    /**
     * fetchRoomSearchQueries 用: 中間集計を結果配列へ変換する。
     * 同一idに複数pageが畳まれた場合、同一クエリは clicks/impressions を合算済みなので、
     * position はインプレッション加重平均で再集計し、room毎に clicks 降順で上位20件に切る。
     *
     * @param array<int, array<string, array{clicks:int, impressions:int, posWeighted:float}>> $acc
     * @return array<int, array<int, array{query:string, clicks:int, impressions:int, position:?float}>>
     */
    public static function finalizeRoomSearchQueries(array $acc): array
    {
        $result = [];
        foreach ($acc as $id => $queries) {
            $list = [];
            foreach ($queries as $query => $v) {
                $list[] = [
                    'query' => (string)$query,
                    'clicks' => $v['clicks'],
                    'impressions' => $v['impressions'],
                    'position' => $v['impressions'] > 0
                        ? round($v['posWeighted'] / $v['impressions'], 2)
                        : null,
                ];
            }
            // clicks 降順 上位20件
            usort($list, static fn($a, $b) => $b['clicks'] <=> $a['clicks']);
            $result[$id] = array_slice($list, 0, 20);
        }

        return $result;
    }

    /**
     * GA4: [pagePath, pageReferrer] × screenPageViews 行を open_chat_id × referrer 別の
     * 中間集計へ加算する（fetchRoomReferrers 用）。
     * pageReferrer が空/'(not set)' の場合は '(direct)' に正規化し、190字に丸める。
     *
     * @param array<int, array<string, mixed>> $rows GA4 runReport の rows
     * @param array<int, array<string, int>> $acc open_chat_id => referrer => pageviews
     * @return array<int, array<string, int>>
     */
    public static function accumulateRoomReferrers(array $rows, array $acc): array
    {
        foreach ($rows as $row) {
            $path = (string)($row['dimensionValues'][0]['value'] ?? '');
            $id = self::extractOpenChatId($path);
            if ($id === null) {
                continue;
            }
            $referrer = self::normalizeReferrer((string)($row['dimensionValues'][1]['value'] ?? ''));
            $pv = (int)round((float)($row['metricValues'][0]['value'] ?? 0));

            $acc[$id][$referrer] = ($acc[$id][$referrer] ?? 0) + $pv;
        }

        return $acc;
    }

    /**
     * fetchRoomReferrers 用: 中間集計を結果配列へ変換する（room毎に pageviews 降順で上位20件）。
     *
     * @param array<int, array<string, int>> $acc
     * @return array<int, array<int, array{referrer:string, pageviews:int}>>
     */
    public static function finalizeRoomReferrers(array $acc): array
    {
        $result = [];
        foreach ($acc as $id => $referrers) {
            $list = [];
            foreach ($referrers as $referrer => $pv) {
                $list[] = ['referrer' => (string)$referrer, 'pageviews' => $pv];
            }
            // pv 降順 上位20件
            usort($list, static fn($a, $b) => $b['pageviews'] <=> $a['pageviews']);
            $result[$id] = array_slice($list, 0, 20);
        }

        return $result;
    }

    /**
     * GA4: [pagePath, linkDomain, sessionDefaultChannelGroup] × eventCount 行から、
     * Organic Search セッション由来の参加リンク押下だけを open_chat_id 別に合算する
     * （fetchJumpClicksByChannel 用）。
     *
     * @param array<int, array<string, mixed>> $rows GA4 runReport の rows
     * @return array<int, int> open_chat_id => organicJumpClicks
     */
    public static function aggregateJumpClicksByChannel(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $path = (string)($row['dimensionValues'][0]['value'] ?? '');
            $linkDomain = (string)($row['dimensionValues'][1]['value'] ?? '');
            $channel = (string)($row['dimensionValues'][2]['value'] ?? '');
            // 参加リンク（line.me）以外のクリックは除外
            if (stripos($linkDomain, 'line.me') === false) {
                continue;
            }
            // Organic Search セッション由来のみ
            if ($channel !== 'Organic Search') {
                continue;
            }
            $id = self::extractJumpOpenChatId($path);
            if ($id === null) {
                continue;
            }
            $clicks = (int)round((float)($row['metricValues'][0]['value'] ?? 0));
            $result[$id] = ($result[$id] ?? 0) + $clicks;
        }

        return $result;
    }

    /**
     * GA4 pageReferrer を保存・表示しやすい形に正規化する。
     * 空 / '(not set)' は '(direct)'。190字を超える場合は丸める。
     */
    private static function normalizeReferrer(string $referrer): string
    {
        $referrer = trim($referrer);
        if ($referrer === '' || $referrer === '(not set)') {
            return '(direct)';
        }
        return mb_strlen($referrer) > 190 ? mb_substr($referrer, 0, 190) : $referrer;
    }

    /**
     * 本家 ja セクション以外（/tw, /th ロケール配下）の URL/パスか。
     *
     * GSC/GA4 のプロパティはドメイン全体（/tw・/th も同一ドメインのサブパス）なので、
     * ja 専用のαに tw/th の流入が混ざる。さらに /th/oc/{id} は PATH_ID_PATTERN が
     * /oc/{id} に一致して**別ロケールの部屋idをja部屋idへ誤って畳む**。これを弾く。
     */
    private static function isOtherLocalePath(string $urlOrPath): bool
    {
        $path = $urlOrPath;
        if (preg_match('#^https?://[^/]+(/.*)$#', $urlOrPath, $m)) {
            $path = $m[1];
        }
        return (bool)preg_match('#^/(?:tw|th)(?:/|$)#', $path);
    }

    /**
     * pagePath / page URL から /openchat/{id} の数値idを抽出。tw/th ロケール配下は除外（ja専用）。
     */
    private static function extractOpenChatId(string $path): ?int
    {
        if (self::isOtherLocalePath($path)) {
            return null;
        }
        if (preg_match(self::PATH_ID_PATTERN, $path, $m)) {
            $id = (int)$m[1];
            return $id > 0 ? $id : null;
        }
        return null;
    }

    /**
     * /oc/{id}/jump の id を抽出（参加リンク計測ページ専用）。tw/th 配下は除外。
     */
    private static function extractJumpOpenChatId(string $path): ?int
    {
        if (self::isOtherLocalePath($path)) {
            return null;
        }
        if (preg_match('#/oc/(\d+)/jump#', $path, $m)) {
            $id = (int)$m[1];
            return $id > 0 ? $id : null;
        }
        return null;
    }
}

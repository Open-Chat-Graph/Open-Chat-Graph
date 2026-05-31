<?php

declare(strict_types=1);

namespace App\Services\Alpha;

use App\Config\SecretsConfig;

/**
 * Alpha Labs 用 Google API クライアント（GA4 Data API / Search Console API）。
 *
 * composer 依存を増やさないため、Google公式SDKは使わず「生HTTP」で実装する。
 * 認証は OAuth（installed app）の refresh_token 方式（oc-pdca と同じ資格情報）。
 * https://oauth2.googleapis.com/token に refresh_token を投げてアクセストークンを得る。
 * 1つのトークンで GA4(analytics.readonly) と GSC(webmasters.readonly) 両方にアクセスできる。
 *
 * 取得対象は本家 openchat-review.me の /oc/{id} 詳細ページのアクセス・検索流入。
 * pagePath / page から /oc/{数字}（念のため /openchat/{数字} も）の id を抽出し、
 * open_chat_id 別に集計して返す。
 *
 * ※ creds 未設定時はこのクラスを生成しないこと（呼び出し側でガードする）。
 */
class AlphaGaClient
{
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    /** 本家詳細ページ /oc/{id}（念のため /openchat/{id} も）から数字idを拾う */
    private const PATH_ID_PATTERN = '#/(?:oc|openchat)/(\d+)#';

    private string $clientId;
    private string $clientSecret;
    private string $refreshToken;
    private string $ga4PropertyId;
    private string $gscSiteUrl;
    private ?string $cachedToken = null;

    /**
     * @throws \RuntimeException 設定が欠けている場合
     */
    public function __construct()
    {
        $this->clientId = SecretsConfig::$googleApiClientId;
        $this->clientSecret = SecretsConfig::$googleApiClientSecret;
        $this->refreshToken = SecretsConfig::$googleApiRefreshToken;
        $this->ga4PropertyId = SecretsConfig::$ga4PropertyId;
        $this->gscSiteUrl = SecretsConfig::$gscSiteUrl;

        if ($this->clientId === '' || $this->clientSecret === '' || $this->refreshToken === '') {
            throw new \RuntimeException('Google OAuth credentials are not configured');
        }
    }

    /**
     * GSC: 期間内の /openchat/{id} 検索流入を open_chat_id 別に集計。
     *
     * position はインプレッション加重平均で再集計する（複数pathが同一idに畳まれる場合に備える）。
     *
     * @return array<int, array{clicks:int, impressions:int, position:?float}>
     */
    public function fetchSearchAnalytics(string $startDate, string $endDate): array
    {
        $token = $this->getAccessToken();

        $url = 'https://searchconsole.googleapis.com/webmasters/v3/sites/'
            . rawurlencode($this->gscSiteUrl) . '/searchAnalytics/query';

        // GSC は最大25000行/リクエスト。startRow でページングする。
        $rowLimit = 25000;
        $startRow = 0;

        // open_chat_id => [clicks, impressions, positionWeightedSum]
        $acc = [];

        while (true) {
            $body = [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => ['page'],
                'rowLimit' => $rowLimit,
                'startRow' => $startRow,
            ];

            $res = $this->httpPostJson($url, $body, $token);
            $rows = $res['rows'] ?? [];
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $page = $row['keys'][0] ?? '';
                $id = $this->extractOpenChatId((string)$page);
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

            if (count($rows) < $rowLimit) {
                break;
            }
            $startRow += $rowLimit;
        }

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
     * GA4: /oc(または/openchat)/{id} 詳細ページの open_chat_id 別 指標をまとめて取得。
     *
     * 1リクエストで pagePath ディメンション × 複数メトリクスを引く:
     *   - screenPageViews        純PV
     *   - activeUsers            ユニークユーザー(UU)
     *   - userEngagementDuration エンゲージ秒(合計) … 平均は /activeUsers で算出
     *
     * 平均エンゲージ秒は「Σ(userEngagementDuration) / Σ(activeUsers)」で id 別に算出する
     * （同一idに複数pathが畳まれる場合に備え、合算してから割る）。
     *
     * @return array<int, array{pageviews:int, activeUsers:int, engagementSeconds:?float}>
     */
    public function fetchRoomMetrics(string $startDate, string $endDate): array
    {
        $token = $this->getAccessToken();

        $url = 'https://analyticsdata.googleapis.com/v1beta/properties/'
            . rawurlencode($this->ga4PropertyId) . ':runReport';

        $body = [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'dimensions' => [['name' => 'pagePath']],
            'metrics' => [
                ['name' => 'screenPageViews'],
                ['name' => 'activeUsers'],
                ['name' => 'userEngagementDuration'],
            ],
            'limit' => 250000,
            'keepEmptyRows' => false,
        ];

        $res = $this->httpPostJson($url, $body, $token);

        // id => [pv, uu, engagementSum]
        $acc = [];
        foreach (($res['rows'] ?? []) as $row) {
            $path = $row['dimensionValues'][0]['value'] ?? '';
            $id = $this->extractOpenChatId((string)$path);
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
     * GA4: /oc/{id}/jump（参加リンク押下）ページの eventName=click & linkDomain=line.me の
     * eventCount を open_chat_id 別に集計する（README の外部リンク計測例に準拠）。
     *
     * pagePath が /oc/{id}/jump 形なので、その id を JUMP_PATH_ID_PATTERN で抽出する。
     *
     * @return array<int, int> open_chat_id => jumpClicks
     */
    public function fetchJumpClicks(string $startDate, string $endDate): array
    {
        $token = $this->getAccessToken();

        $url = 'https://analyticsdata.googleapis.com/v1beta/properties/'
            . rawurlencode($this->ga4PropertyId) . ':runReport';

        $body = [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'dimensions' => [['name' => 'pagePath'], ['name' => 'linkDomain']],
            'metrics' => [['name' => 'eventCount']],
            'dimensionFilter' => [
                'filter' => [
                    'fieldName' => 'eventName',
                    'stringFilter' => ['matchType' => 'EXACT', 'value' => 'click'],
                ],
            ],
            'limit' => 250000,
            'keepEmptyRows' => false,
        ];

        $res = $this->httpPostJson($url, $body, $token);

        $result = [];
        foreach (($res['rows'] ?? []) as $row) {
            $path = $row['dimensionValues'][0]['value'] ?? '';
            $linkDomain = (string)($row['dimensionValues'][1]['value'] ?? '');
            // 参加リンク（line.me）以外のクリックは除外
            if (stripos($linkDomain, 'line.me') === false) {
                continue;
            }
            $id = $this->extractJumpOpenChatId((string)$path);
            if ($id === null) {
                continue;
            }
            $clicks = (int)round((float)($row['metricValues'][0]['value'] ?? 0));
            $result[$id] = ($result[$id] ?? 0) + $clicks;
        }

        return $result;
    }

    /**
     * GA4: 非部屋ページ（トップ '/' / おすすめ '/recommend/{tag}'）の PV/UU を path 別に集計。
     *
     * 返すキーは正規化済み path（'/', '/recommend/下ネタ' 等）。label は path 末尾(tag)を入れる。
     *
     * @return array<string, array{label:string, pageviews:int, activeUsers:int}>
     */
    public function fetchPageMetrics(string $startDate, string $endDate): array
    {
        $token = $this->getAccessToken();

        $url = 'https://analyticsdata.googleapis.com/v1beta/properties/'
            . rawurlencode($this->ga4PropertyId) . ':runReport';

        $body = [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'dimensions' => [['name' => 'pagePath']],
            'metrics' => [
                ['name' => 'screenPageViews'],
                ['name' => 'activeUsers'],
            ],
            'limit' => 250000,
            'keepEmptyRows' => false,
        ];

        $res = $this->httpPostJson($url, $body, $token);

        $acc = [];
        foreach (($res['rows'] ?? []) as $row) {
            $path = (string)($row['dimensionValues'][0]['value'] ?? '');
            $norm = $this->normalizePageScopePath($path);
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
     * GSC: 非部屋ページ（トップ / おすすめ）の検索流入を path 別に集計（page ディメンション）。
     *
     * @return array<string, array{clicks:int, impressions:int, position:?float}>
     */
    public function fetchPageSearchAnalytics(string $startDate, string $endDate): array
    {
        $token = $this->getAccessToken();

        $url = 'https://searchconsole.googleapis.com/webmasters/v3/sites/'
            . rawurlencode($this->gscSiteUrl) . '/searchAnalytics/query';

        $rowLimit = 25000;
        $startRow = 0;
        $acc = []; // path => [clicks, impressions, posWeighted]

        while (true) {
            $body = [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => ['page'],
                'rowLimit' => $rowLimit,
                'startRow' => $startRow,
            ];

            $res = $this->httpPostJson($url, $body, $token);
            $rows = $res['rows'] ?? [];
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $page = (string)($row['keys'][0] ?? '');
                $norm = $this->normalizePageScopePath($page);
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

            if (count($rows) < $rowLimit) {
                break;
            }
            $startRow += $rowLimit;
        }

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
     * GSC: 上位検索クエリ（query ディメンション）を取得。
     *
     * @return array<int, array{query:string, clicks:int, impressions:int, position:?float}>
     */
    public function fetchTopSearchQueries(string $startDate, string $endDate, int $limit = 1000): array
    {
        $token = $this->getAccessToken();

        $url = 'https://searchconsole.googleapis.com/webmasters/v3/sites/'
            . rawurlencode($this->gscSiteUrl) . '/searchAnalytics/query';

        $rowLimit = min(max(1, $limit), 25000);

        $body = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => ['query'],
            'rowLimit' => $rowLimit,
            'startRow' => 0,
        ];

        $res = $this->httpPostJson($url, $body, $token);
        $rows = $res['rows'] ?? [];

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
     * pagePath / page URL から /openchat/{id} の数値idを抽出。
     */
    private function extractOpenChatId(string $path): ?int
    {
        if (preg_match(self::PATH_ID_PATTERN, $path, $m)) {
            $id = (int)$m[1];
            return $id > 0 ? $id : null;
        }
        return null;
    }

    /**
     * /oc/{id}/jump の id を抽出（参加リンク計測ページ専用）。
     */
    private function extractJumpOpenChatId(string $path): ?int
    {
        if (preg_match('#/oc/(\d+)/jump#', $path, $m)) {
            $id = (int)$m[1];
            return $id > 0 ? $id : null;
        }
        return null;
    }

    /**
     * 非部屋ページ path をトップ '/' / おすすめ '/recommend/{tag}' に正規化する。
     * クエリ文字列やトレイリングスラッシュ・ドメインを除去し、対象外は null。
     *
     * @return array{path:string, label:string}|null
     */
    private function normalizePageScopePath(string $raw): ?array
    {
        // 完全URLなら path 部だけ取り出す
        $path = $raw;
        if (preg_match('#^https?://[^/]+(/.*)$#', $raw, $m)) {
            $path = $m[1];
        } elseif (preg_match('#^https?://[^/]+$#', $raw)) {
            $path = '/';
        }
        // クエリ/フラグメント除去
        $path = preg_replace('/[?#].*$/', '', $path) ?? $path;

        // トップ
        if ($path === '' || $path === '/' || $path === '/index.html') {
            return ['path' => '/', 'label' => 'トップ'];
        }

        // おすすめ /recommend/{tag}（末尾スラッシュ許容）
        if (preg_match('#^/recommend/([^/]+)/?$#', $path, $mm)) {
            $tag = rawurldecode($mm[1]);
            $tag = mb_strlen($tag) > 150 ? mb_substr($tag, 0, 150) : $tag;
            return ['path' => '/recommend/' . $tag, 'label' => $tag];
        }

        return null;
    }

    /**
     * refresh_token を使って OAuth2 アクセストークンを取得する（GA4/GSC共通・1リクエスト内でキャッシュ）。
     */
    private function getAccessToken(): string
    {
        if ($this->cachedToken !== null) {
            return $this->cachedToken;
        }

        $res = $this->httpPostForm(self::TOKEN_ENDPOINT, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (empty($res['access_token'])) {
            $err = $res['error_description'] ?? ($res['error'] ?? 'unknown');
            throw new \RuntimeException('Failed to refresh access token: ' . (string)$err);
        }

        return $this->cachedToken = (string)$res['access_token'];
    }

    /**
     * JSON ボディの POST（Bearer 認証）。レスポンスを連想配列で返す。
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function httpPostJson(string $url, array $body, string $token): array
    {
        return $this->request($url, (string)json_encode($body), [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ]);
    }

    /**
     * application/x-www-form-urlencoded の POST（トークン交換用）。
     *
     * @param array<string, string> $params
     * @return array<string, mixed>
     */
    private function httpPostForm(string $url, array $params): array
    {
        return $this->request($url, http_build_query($params), [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
    }

    /**
     * 生HTTP POST。curl があれば curl、無ければ stream_context にフォールバック。
     *
     * @param string[] $headers
     * @return array<string, mixed>
     */
    private function request(string $url, string $payload, array $headers): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_CONNECTTIMEOUT => 15,
            ]);
            $raw = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            // PHP 8.0+ では curl_close は不要（8.5で非推奨＝厳格ハンドラが例外化するため呼ばない）

            if ($raw === false || $errno !== 0) {
                throw new \RuntimeException("HTTP request failed: {$error} ({$url})");
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers),
                    'content' => $payload,
                    'timeout' => 120,
                    'ignore_errors' => true,
                ],
            ]);
            $raw = @file_get_contents($url, false, $context);
            $status = 0;
            if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
                $status = (int)$m[1];
            }
            if ($raw === false) {
                throw new \RuntimeException("HTTP request failed: {$url}");
            }
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException("Invalid JSON response (status {$status}) from {$url}: " . substr((string)$raw, 0, 500));
        }

        if ($status >= 400) {
            $msg = $decoded['error']['message'] ?? ($decoded['error_description'] ?? ($decoded['error'] ?? 'unknown'));
            if (is_array($msg)) {
                $msg = json_encode($msg);
            }
            throw new \RuntimeException("HTTP {$status} from {$url}: " . (string)$msg);
        }

        return $decoded;
    }
}

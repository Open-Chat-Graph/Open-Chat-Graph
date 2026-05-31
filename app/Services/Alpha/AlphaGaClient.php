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
     * GA4: 期間内の /openchat/{id} ページビューを open_chat_id 別に集計。
     *
     * @return array<int, int> open_chat_id => pageviews
     */
    public function fetchPageviews(string $startDate, string $endDate): array
    {
        $token = $this->getAccessToken();

        $url = 'https://analyticsdata.googleapis.com/v1beta/properties/'
            . rawurlencode($this->ga4PropertyId) . ':runReport';

        $body = [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'dimensions' => [['name' => 'pagePath']],
            'metrics' => [['name' => 'screenPageViews']],
            'limit' => 250000,
            'keepEmptyRows' => false,
        ];

        $res = $this->httpPostJson($url, $body, $token);

        $result = [];
        foreach (($res['rows'] ?? []) as $row) {
            $path = $row['dimensionValues'][0]['value'] ?? '';
            $id = $this->extractOpenChatId((string)$path);
            if ($id === null) {
                continue;
            }
            $views = (int)round((float)($row['metricValues'][0]['value'] ?? 0));
            $result[$id] = ($result[$id] ?? 0) + $views;
        }

        return $result;
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

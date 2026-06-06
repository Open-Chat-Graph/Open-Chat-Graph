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
 *
 * ## access_token キャッシュ戦略
 *
 * - プロセス内キャッシュ: $cachedToken（リクエスト/バッチ1回内）
 * - プロセス跨ぎキャッシュ: TOKEN_STORE_PATH に JSON で保存（expiry 60秒マージン）
 *   - ストア形式: {"access_token":"...", "expiry":1234567890, "refresh_token":"..."}
 *   - refresh 応答に refresh_token が含まれる場合（Google rotation）はストアを更新して永続化
 *   - ストアの書込が不可な環境では従来どおり SecretsConfig の refresh_token で毎回 refresh
 *
 * ## invalid_grant（refresh_token 失効）時
 * - ストア由来の refresh_token（SecretsConfig 値と異なるもの）で invalid_grant になった場合は、
 *   ストアを破棄して SecretsConfig（local-secrets.php）の値で1回だけフォールバック再試行する
 *   （local-secrets を新トークンに差し替えた後も古いストアが残るケースの自動回復）。
 * - それでも invalid_grant なら明確な RuntimeException を投げてバッチログへ記録する。
 *   OAuth 再同意（ブラウザ操作）が必要なためコードでは自動回復できない。
 *
 * ## 同時実行（cron と admin 手動実行など）
 * - refresh が必要になった時点で TOKEN_STORE_PATH.lock に flock(LOCK_EX) して排他する
 *   （本体 JSON は rename で inode が差し替わるため、ロック対象は別の .lock ファイル）。
 * - ロック取得後にストアを再読込（double-checked）し、待機中に他プロセスが
 *   refresh 済みなら HTTP を叩かずそれを返す（rotation の取りこぼし防止）。
 * - fopen/flock 失敗時はロックなしで続行（書込不可環境では従来どおり劣化動作）。
 */
class AlphaGaClient
{
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    /** 本家詳細ページ /oc/{id}（念のため /openchat/{id} も）から数字idを拾う */
    private const PATH_ID_PATTERN = '#/(?:oc|openchat)/(\d+)#';

    /**
     * プロセス跨ぎトークンストアのパス。
     * storage/ja は drwxrwxrwx（アプリ書込可）。
     * コミット対象外（.gitignore に追記済み）。
     */
    private const TOKEN_STORE_PATH = __DIR__ . '/../../../storage/ja/alpha_ga_token.json';

    /** access_token の有効期限の何秒前から refresh するか（通常 3600s - 60s） */
    private const EXPIRY_MARGIN_SECONDS = 60;

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

        // ja 専用: tw/th ロケール配下のページに着地したクエリ（タイ語/中国語など）を除外する。
        // query 次元のみだと page で畳めないので GSC 側の page フィルタで弾く。
        $body = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => ['query'],
            'dimensionFilterGroups' => [[
                'filters' => [
                    ['dimension' => 'page', 'operator' => 'excludingRegex', 'expression' => '/(?:tw|th)(?:/|$)'],
                ],
            ]],
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
     * GSC: 期間内の /oc(または/openchat)/{id} 詳細ページに流入した検索クエリを open_chat_id 別に集計。
     *
     * dimensions=[page, query] で叩き、page を PATH_ID_PATTERN で open_chat_id に畳む。
     * 同一idに複数pageが畳まれた場合、同一クエリは clicks/impressions を合算し
     * position はインプレッション加重平均で再集計する。
     * room毎に clicks 降順で上位20件に切る。
     *
     * GSC は最大25000行/リクエスト。startRow でページングする。
     *
     * @return array<int, array<int, array{query:string, clicks:int, impressions:int, position:?float}>>
     */
    public function fetchRoomSearchQueries(string $startDate, string $endDate): array
    {
        $token = $this->getAccessToken();

        $url = 'https://searchconsole.googleapis.com/webmasters/v3/sites/'
            . rawurlencode($this->gscSiteUrl) . '/searchAnalytics/query';

        $rowLimit = 25000;
        $startRow = 0;

        // open_chat_id => query => [clicks, impressions, posWeighted]
        $acc = [];

        while (true) {
            $body = [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => ['page', 'query'],
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
                $query = (string)($row['keys'][1] ?? '');
                if ($query === '') {
                    continue;
                }
                $id = $this->extractOpenChatId($page);
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

            if (count($rows) < $rowLimit) {
                break;
            }
            $startRow += $rowLimit;
        }

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
     * GA4: /oc(または/openchat)/{id} 詳細ページのリファラ元を open_chat_id 別に集計。
     *
     * dimensions=[pagePath, pageReferrer], metrics=[screenPageViews] で叩き、
     * pagePath を PATH_ID_PATTERN で open_chat_id に畳む。
     * pageReferrer が空/'(not set)' の場合は '(direct)' に正規化し、190字に丸める。
     * room毎に pageviews 降順で上位20件に切る。
     *
     * @return array<int, array<int, array{referrer:string, pageviews:int}>>
     */
    public function fetchRoomReferrers(string $startDate, string $endDate): array
    {
        $token = $this->getAccessToken();

        $url = 'https://analyticsdata.googleapis.com/v1beta/properties/'
            . rawurlencode($this->ga4PropertyId) . ':runReport';

        $pageLimit = 100000;
        $offset = 0;

        // open_chat_id => referrer => pageviews
        $acc = [];

        while (true) {
            $body = [
                'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
                'dimensions' => [['name' => 'pagePath'], ['name' => 'pageReferrer']],
                'metrics' => [['name' => 'screenPageViews']],
                'orderBys' => [[
                    'metric' => ['metricName' => 'screenPageViews'],
                    'desc' => true,
                ]],
                'limit' => $pageLimit,
                'offset' => $offset,
                'keepEmptyRows' => false,
            ];

            $res = $this->httpPostJson($url, $body, $token);
            $rows = $res['rows'] ?? [];
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $path = (string)($row['dimensionValues'][0]['value'] ?? '');
                $id = $this->extractOpenChatId($path);
                if ($id === null) {
                    continue;
                }
                $referrer = $this->normalizeReferrer((string)($row['dimensionValues'][1]['value'] ?? ''));
                $pv = (int)round((float)($row['metricValues'][0]['value'] ?? 0));

                $acc[$id][$referrer] = ($acc[$id][$referrer] ?? 0) + $pv;
            }

            if (count($rows) < $pageLimit) {
                break;
            }
            $offset += $pageLimit;
        }

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
     * GA4: 参加リンク押下(/oc/{id}/jump の click & line.me)のうち
     * セッションのチャネルが Organic Search のものだけを open_chat_id 別に合算する。
     *
     * fetchJumpClicks に sessionDefaultChannelGroup ディメンションを足したバリアント。
     * 既存 fetchJumpClicks の戻り型は変えず、organic 分だけをこのメソッドで別取得する。
     *
     * @return array<int, int> open_chat_id => organicJumpClicks
     */
    public function fetchJumpClicksByChannel(string $startDate, string $endDate): array
    {
        $token = $this->getAccessToken();

        $url = 'https://analyticsdata.googleapis.com/v1beta/properties/'
            . rawurlencode($this->ga4PropertyId) . ':runReport';

        $body = [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'dimensions' => [
                ['name' => 'pagePath'],
                ['name' => 'linkDomain'],
                ['name' => 'sessionDefaultChannelGroup'],
            ],
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
            $id = $this->extractJumpOpenChatId($path);
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
    private function normalizeReferrer(string $referrer): string
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
    private function isOtherLocalePath(string $urlOrPath): bool
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
    private function extractOpenChatId(string $path): ?int
    {
        if ($this->isOtherLocalePath($path)) {
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
    private function extractJumpOpenChatId(string $path): ?int
    {
        if ($this->isOtherLocalePath($path)) {
            return null;
        }
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

        // tw/th ロケール配下は ja 専用αの対象外
        if (preg_match('#^/(?:tw|th)(?:/|$)#', $path)) {
            return null;
        }

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
     * refresh_token を使って OAuth2 アクセストークンを取得する（GA4/GSC共通）。
     *
     * 優先順位:
     * 1. プロセス内キャッシュ ($cachedToken) が有れば即返す。
     * 2. トークンストア (TOKEN_STORE_PATH) のキャッシュが有効期限内ならそれを返す。
     *    （rename がアトミックなのでロックレス読みで安全）
     * 3. flock で排他してから Google に refresh して新しい access_token を取得し、
     *    ストアに保存する（refreshAndStoreLocked()）。
     *
     * ストア書込失敗はログのみで握りつぶし、毎回 refresh する従来動作に劣化フォールバック。
     *
     * @throws \RuntimeException invalid_grant（refresh_token 失効）または HTTP エラー時
     */
    private function getAccessToken(): string
    {
        // 1. プロセス内キャッシュ
        if ($this->cachedToken !== null) {
            return $this->cachedToken;
        }

        // 2. プロセス跨ぎストアキャッシュ（高速パス: ロックなし）
        $storedToken = $this->extractValidAccessToken($this->loadTokenStore());
        if ($storedToken !== null) {
            return $this->cachedToken = $storedToken;
        }

        // 3. refresh が必要 → flock で排他して refresh＋ストア保存
        return $this->refreshAndStoreLocked();
    }

    /**
     * flock で排他しながら refresh_token → access_token の交換とストア保存を行う。
     *
     * - ロック対象は TOKEN_STORE_PATH.lock（本体 JSON は saveTokenStore の rename で
     *   inode が差し替わるため、本体 fd への flock は無効）。
     * - flock はブロッキング（呼び出し元は batch/admin のみで低頻度）。
     * - fopen/flock 失敗時はロックなしで続行（書込不可環境では劣化、fatal にしない）。
     * - ロック取得後にストアを再読込（double-checked）し、待機中に他プロセスが
     *   refresh 済みで有効なら HTTP を叩かずそれを返す。
     * - ストア由来の refresh_token（SecretsConfig 値と異なるもの）が invalid_grant の場合は、
     *   ストアを破棄して SecretsConfig 値で1回だけ再試行する（自動回復）。
     *
     * @throws \RuntimeException invalid_grant（refresh_token 失効）または HTTP エラー時
     */
    private function refreshAndStoreLocked(): string
    {
        $lockPath = self::TOKEN_STORE_PATH . '.lock';
        $fp = @fopen($lockPath, 'c');
        $locked = $fp !== false && @flock($fp, LOCK_EX);

        try {
            // double-checked: ロック待機中に他プロセスが refresh 済みならそれを返す
            $stored = $this->loadTokenStore();
            $storedToken = $this->extractValidAccessToken($stored);
            if ($storedToken !== null) {
                return $this->cachedToken = $storedToken;
            }

            // refresh_token はストアに保持されている場合はそちらを使う（rotation 対応）
            $refreshToken = $this->refreshToken; // SecretsConfig のデフォルト
            $usedStoredToken = false;
            if (
                $stored !== null
                && isset($stored['refresh_token'])
                && is_string($stored['refresh_token'])
                && $stored['refresh_token'] !== ''
            ) {
                $refreshToken = $stored['refresh_token'];
                // SecretsConfig 値と同じならフォールバックしても同じ失敗を繰り返すだけなのでフラグを立てない
                $usedStoredToken = $stored['refresh_token'] !== $this->refreshToken;
            }

            $res = $this->requestToken($refreshToken);

            // ストア由来の refresh_token が invalid_grant → ストアを破棄して
            // SecretsConfig（local-secrets.php 差し替え後の新トークン想定）で1回だけ再試行
            if ($this->isInvalidGrant($res) && $usedStoredToken) {
                $this->clearTokenStore();
                $refreshToken = $this->refreshToken;
                $res = $this->requestToken($refreshToken);
            }

            if (empty($res['access_token'])) {
                $err = $res['error_description'] ?? ($res['error'] ?? 'unknown');
                $errStr = (string)$err;
                // invalid_grant はコードで自動回復不可（OAuth 再同意が必要）
                if ($this->isInvalidGrant($res)) {
                    throw new \RuntimeException(
                        'refresh_tokenが失効しています。OAuth再同意が必要です（ブラウザでGoogle認証→token.jsonを更新し、local-secrets.phpの$googleApiRefreshTokenを差し替えてください）。詳細: ' . $errStr
                    );
                }
                throw new \RuntimeException('Failed to refresh access token: ' . $errStr);
            }

            $newAccessToken = (string)$res['access_token'];
            $expiresIn = isset($res['expires_in']) ? (int)$res['expires_in'] : 3600;

            // ストアに保存（書込失敗は握りつぶし）
            $newRefreshToken = isset($res['refresh_token']) && is_string($res['refresh_token']) && $res['refresh_token'] !== ''
                ? $res['refresh_token']
                : $refreshToken;

            $this->saveTokenStore([
                'access_token' => $newAccessToken,
                'expiry' => time() + $expiresIn,
                'refresh_token' => $newRefreshToken,
            ]);

            return $this->cachedToken = $newAccessToken;
        } finally {
            if ($fp !== false) {
                if ($locked) {
                    @flock($fp, LOCK_UN);
                }
                @fclose($fp);
            }
        }
    }

    /**
     * token エンドポイントへ refresh_token を POST してレスポンスを返す。
     *
     * @return array<string, mixed>
     * @throws \RuntimeException HTTP 通信自体の失敗時
     */
    private function requestToken(string $refreshToken): array
    {
        return $this->httpPostForm(self::TOKEN_ENDPOINT, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
    }

    /**
     * token エンドポイント応答が invalid_grant（refresh_token 失効）かどうか。
     *
     * @param array<string, mixed> $res
     */
    private function isInvalidGrant(array $res): bool
    {
        return isset($res['error']) && $res['error'] === 'invalid_grant';
    }

    /**
     * ストア内容から有効期限内の access_token を取り出す。無ければ null。
     *
     * @param array<string, mixed>|null $stored
     */
    private function extractValidAccessToken(?array $stored): ?string
    {
        if (
            $stored !== null
            && isset($stored['access_token'], $stored['expiry'])
            && is_string($stored['access_token'])
            && is_int($stored['expiry'])
            && $stored['access_token'] !== ''
            && time() < $stored['expiry'] - self::EXPIRY_MARGIN_SECONDS
        ) {
            return $stored['access_token'];
        }
        return null;
    }

    /**
     * トークンストアを削除する（invalid_grant 自動回復用）。
     * refresh_token が失効している場合は access_token も期限切れ確定のためファイルごと削除する。
     */
    private function clearTokenStore(): void
    {
        @unlink(self::TOKEN_STORE_PATH);
    }

    /**
     * トークンストアを読み込む。存在しない・読めない場合は null を返す。
     *
     * @return array<string, mixed>|null
     */
    private function loadTokenStore(): ?array
    {
        $path = self::TOKEN_STORE_PATH;
        if (!file_exists($path) || !is_readable($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * トークンストアに書き込む。書込失敗時はエラーログのみ（例外を投げない）。
     *
     * @param array<string, mixed> $data
     */
    private function saveTokenStore(array $data): void
    {
        $path = self::TOKEN_STORE_PATH;
        $dir = dirname($path);
        if (!is_dir($dir) || !is_writable($dir)) {
            error_log('[AlphaGaClient] トークンストアのディレクトリが書込不可のためキャッシュをスキップ: ' . $dir);
            return;
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            error_log('[AlphaGaClient] トークンストアのJSON生成に失敗');
            return;
        }
        // アトミック書込（tmpファイル→rename）
        $tmp = $path . '.tmp.' . getmypid();
        if (@file_put_contents($tmp, $json) === false) {
            error_log('[AlphaGaClient] トークンストアの書込に失敗: ' . $tmp);
            return;
        }
        if (!@rename($tmp, $path)) {
            error_log('[AlphaGaClient] トークンストアのrenameに失敗: ' . $tmp . ' -> ' . $path);
            @unlink($tmp);
        }
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

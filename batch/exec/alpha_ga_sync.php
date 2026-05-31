<?php

/**
 * Alpha Labs: 部屋別アクセス/検索流入の日次同期バッチ。
 *
 * 本家 openchat-review.me の GA4(Data API) と Search Console から、
 * /openchat/{id} 詳細ページのアクセス・検索流入を取得し、open_chat_id 別に集計して
 * alpha_room_access_daily に upsert(INSERT ... ON DUPLICATE KEY UPDATE)する。
 *
 * ja(urlRoot=='')専用。既定では直近数日（DEFAULT_DAYS_BACK）を毎回取り直して
 * 上書きする（GAは確定まで数日かかるため、直近を再取得して最終値に寄せる）。
 *
 * ▼ creds 未投入時の挙動:
 *   SecretsConfig の ga4PropertyId / gscSiteUrl / googleApiClientId / googleApiClientSecret /
 *   googleApiRefreshToken が1つでも欠けていれば「何もせず exit 0」で安全に空振りする（本番を一切触らない）。
 *
 * ▼ creds 投入後の実行手順:
 *   1. local-secrets.php に GA4/GSC と OAuth(client_id/secret/refresh_token)を設定
 *      （SecretsConfig 冒頭コメント参照。値は oc-pdca の credentials.json/token.json から転記）。
 *      OAuthアカウントが GA4 プロパティと Search Console に
 *      閲覧者として共有しておく。
 *   2. テーブルを反映（加算のみ。ディレクターが実施）:
 *        docker compose exec app php batch/exec/sync_mysql_schema.php --dry-run
 *        docker compose exec app php batch/exec/sync_mysql_schema.php
 *   3. 単発実行で疎通確認:
 *        docker compose exec app php batch/exec/alpha_ga_sync.php
 *      （--days=N で遡及日数を指定可。例 初回バックフィル: --days=90）
 *   4. 日次cron(cron_crawling.php の日次処理後)から自動起動される
 *      （creds 設定時のみ。未設定なら起動しても即 exit 0）。
 *
 * 引数:
 *   --days=N   取得する遡及日数（既定 DEFAULT_DAYS_BACK）。前日を終端として N 日分。
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Config\SecretsConfig;
use App\Models\Repositories\DB;
use App\Services\Alpha\AlphaGaClient;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Utility\CronUtility;
use ExceptionHandler\ExceptionHandler;
use Shared\MimimalCmsConfig;

// Alpha は ja のみ稼働
MimimalCmsConfig::$urlRoot = '';

/** 毎回取り直す直近日数（GAの確定遅延に追従するため複数日を再取得して上書き） */
const DEFAULT_DAYS_BACK = 4;

// creds 未設定なら何もせず正常終了（本番を一切触らない）
if (!SecretsConfig::isGoogleAnalyticsConfigured()) {
    CronUtility::addCronLog('【Alpha GA同期】スキップ: GA4/GSC creds 未設定');
    exit(0);
}

try {
    $daysBack = DEFAULT_DAYS_BACK;
    foreach ($argv as $arg) {
        if (preg_match('/^--days=(\d+)$/', $arg, $m)) {
            $daysBack = max(1, (int)$m[1]);
        }
    }

    // 前日を終端に N 日分（GA は当日分が未確定なので前日まで）
    $endDate = (new \DateTime('yesterday'))->format('Y-m-d');
    $startDate = (new \DateTime('yesterday'))->modify('-' . ($daysBack - 1) . ' day')->format('Y-m-d');

    $client = new AlphaGaClient();

    // 期間まとめて取得してから、日付ごとに按分はせず「期間内合計を各日付に書く」のではなく、
    // 日次の粒度で保存するため日付ごとにAPIを叩く（GA4/GSC とも単日 dateRange で集計）。
    $period = new \DatePeriod(
        new \DateTime($startDate),
        new \DateInterval('P1D'),
        (new \DateTime($endDate))->modify('+1 day')
    );

    DB::connect();

    $totalUpserts = 0;
    $errors = [];

    foreach ($period as $day) {
        $date = $day->format('Y-m-d');

        // ---- 1) 部屋別 (alpha_room_access_daily) ----
        // open_chat_id => 各指標 を統合
        $merged = [];

        // GA4: PV/UU/平均エンゲージ秒（1リクエストで取得）
        try {
            foreach ($client->fetchRoomMetrics($date, $date) as $id => $m) {
                $merged[$id]['pageviews'] = $m['pageviews'];
                $merged[$id]['active_users'] = $m['activeUsers'];
                $merged[$id]['engagement_seconds'] = $m['engagementSeconds'];
            }
        } catch (\Throwable $e) {
            $errors[] = "GA4 room {$date}: " . $e->getMessage();
        }

        // GA4: 参加リンク押下数（/oc/{id}/jump の click & line.me）
        try {
            foreach ($client->fetchJumpClicks($date, $date) as $id => $jc) {
                $merged[$id]['jump_clicks'] = $jc;
            }
        } catch (\Throwable $e) {
            $errors[] = "GA4 jump {$date}: " . $e->getMessage();
        }

        // GA4: 参加リンク押下のうち Organic Search セッション由来の件数
        try {
            foreach ($client->fetchJumpClicksByChannel($date, $date) as $id => $jco) {
                $merged[$id]['jump_clicks_organic'] = $jco;
            }
        } catch (\Throwable $e) {
            $errors[] = "GA4 jump-organic {$date}: " . $e->getMessage();
        }

        // GSC: 検索クリック/表示/順位
        try {
            foreach ($client->fetchSearchAnalytics($date, $date) as $id => $s) {
                $merged[$id]['search_clicks'] = $s['clicks'];
                $merged[$id]['search_impressions'] = $s['impressions'];
                $merged[$id]['search_position'] = $s['position'];
            }
        } catch (\Throwable $e) {
            $errors[] = "GSC room {$date}: " . $e->getMessage();
        }

        foreach ($merged as $id => $row) {
            DB::execute(
                "INSERT INTO alpha_room_access_daily
                    (open_chat_id, `date`, pageviews, search_clicks, search_impressions, search_position,
                     active_users, jump_clicks, jump_clicks_organic, engagement_seconds)
                 VALUES (:id, :date, :pv, :clicks, :impr, :pos, :uu, :jump, :jump_organic, :eng)
                 ON DUPLICATE KEY UPDATE
                    pageviews = VALUES(pageviews),
                    search_clicks = VALUES(search_clicks),
                    search_impressions = VALUES(search_impressions),
                    search_position = VALUES(search_position),
                    active_users = VALUES(active_users),
                    jump_clicks = VALUES(jump_clicks),
                    jump_clicks_organic = VALUES(jump_clicks_organic),
                    engagement_seconds = VALUES(engagement_seconds)",
                [
                    'id' => (int)$id,
                    'date' => $date,
                    'pv' => (int)($row['pageviews'] ?? 0),
                    'clicks' => (int)($row['search_clicks'] ?? 0),
                    'impr' => (int)($row['search_impressions'] ?? 0),
                    'pos' => $row['search_position'] ?? null,
                    'uu' => (int)($row['active_users'] ?? 0),
                    'jump' => (int)($row['jump_clicks'] ?? 0),
                    'jump_organic' => (int)($row['jump_clicks_organic'] ?? 0),
                    'eng' => $row['engagement_seconds'] ?? null,
                ]
            );
            $totalUpserts++;
        }

        // ---- 1b) 部屋別 流入検索クエリ (alpha_room_search_query_daily) ----
        try {
            foreach ($client->fetchRoomSearchQueries($date, $date) as $id => $queries) {
                foreach ($queries as $q) {
                    DB::execute(
                        "INSERT INTO alpha_room_search_query_daily
                            (open_chat_id, query, `date`, clicks, impressions, position)
                         VALUES (:id, :q, :date, :clicks, :impr, :pos)
                         ON DUPLICATE KEY UPDATE
                            clicks = VALUES(clicks),
                            impressions = VALUES(impressions),
                            position = VALUES(position)",
                        [
                            'id' => (int)$id,
                            'q' => $q['query'],
                            'date' => $date,
                            'clicks' => $q['clicks'],
                            'impr' => $q['impressions'],
                            'pos' => $q['position'],
                        ]
                    );
                    $totalUpserts++;
                }
            }
        } catch (\Throwable $e) {
            $errors[] = "GSC room-query {$date}: " . $e->getMessage();
        }

        // ---- 1c) 部屋別 リファラ元 (alpha_room_referrer_daily) ----
        try {
            foreach ($client->fetchRoomReferrers($date, $date) as $id => $referrers) {
                foreach ($referrers as $r) {
                    DB::execute(
                        "INSERT INTO alpha_room_referrer_daily
                            (open_chat_id, referrer, `date`, pageviews)
                         VALUES (:id, :ref, :date, :pv)
                         ON DUPLICATE KEY UPDATE
                            pageviews = VALUES(pageviews)",
                        [
                            'id' => (int)$id,
                            'ref' => $r['referrer'],
                            'date' => $date,
                            'pv' => $r['pageviews'],
                        ]
                    );
                    $totalUpserts++;
                }
            }
        } catch (\Throwable $e) {
            $errors[] = "GA4 room-referrer {$date}: " . $e->getMessage();
        }

        // ---- 2) 非部屋ページ (alpha_page_access_daily): トップ / おすすめ ----
        $pages = [];

        try {
            foreach ($client->fetchPageMetrics($date, $date) as $path => $m) {
                $pages[$path]['label'] = $m['label'];
                $pages[$path]['pageviews'] = $m['pageviews'];
                $pages[$path]['active_users'] = $m['activeUsers'];
            }
        } catch (\Throwable $e) {
            $errors[] = "GA4 page {$date}: " . $e->getMessage();
        }

        try {
            foreach ($client->fetchPageSearchAnalytics($date, $date) as $path => $s) {
                $pages[$path]['search_clicks'] = $s['clicks'];
                $pages[$path]['search_impressions'] = $s['impressions'];
                $pages[$path]['search_position'] = $s['position'];
            }
        } catch (\Throwable $e) {
            $errors[] = "GSC page {$date}: " . $e->getMessage();
        }

        foreach ($pages as $path => $row) {
            DB::execute(
                "INSERT INTO alpha_page_access_daily
                    (path, `date`, label, pageviews, active_users, search_clicks, search_impressions, search_position)
                 VALUES (:path, :date, :label, :pv, :uu, :clicks, :impr, :pos)
                 ON DUPLICATE KEY UPDATE
                    label = VALUES(label),
                    pageviews = VALUES(pageviews),
                    active_users = VALUES(active_users),
                    search_clicks = VALUES(search_clicks),
                    search_impressions = VALUES(search_impressions),
                    search_position = VALUES(search_position)",
                [
                    'path' => (string)$path,
                    'date' => $date,
                    'label' => (string)($row['label'] ?? ''),
                    'pv' => (int)($row['pageviews'] ?? 0),
                    'uu' => (int)($row['active_users'] ?? 0),
                    'clicks' => (int)($row['search_clicks'] ?? 0),
                    'impr' => (int)($row['search_impressions'] ?? 0),
                    'pos' => $row['search_position'] ?? null,
                ]
            );
            $totalUpserts++;
        }

        // ---- 3) 上位検索クエリ (alpha_search_query_daily) ----
        try {
            foreach ($client->fetchTopSearchQueries($date, $date) as $q) {
                DB::execute(
                    "INSERT INTO alpha_search_query_daily
                        (query, `date`, clicks, impressions, position)
                     VALUES (:q, :date, :clicks, :impr, :pos)
                     ON DUPLICATE KEY UPDATE
                        clicks = VALUES(clicks),
                        impressions = VALUES(impressions),
                        position = VALUES(position)",
                    [
                        'q' => $q['query'],
                        'date' => $date,
                        'clicks' => $q['clicks'],
                        'impr' => $q['impressions'],
                        'pos' => $q['position'],
                    ]
                );
                $totalUpserts++;
            }
        } catch (\Throwable $e) {
            $errors[] = "GSC query {$date}: " . $e->getMessage();
        }
    }

    CronUtility::addCronLog(
        "【Alpha GA同期】完了 period={$startDate}〜{$endDate} upserts={$totalUpserts}"
            . (empty($errors) ? '' : ' errors=' . implode(' | ', $errors))
    );

    // 部分エラー（特定日のAPI失敗）は Discord に通知だけして全体は成功扱い
    if (!empty($errors)) {
        AdminTool::sendDiscordNotify('【Alpha GA同期】部分エラー: ' . implode(' | ', $errors));
    }
} catch (\Throwable $e) {
    ExceptionHandler::errorLog($e);
    $message = CronUtility::addCronLog('【Alpha GA同期】失敗: ' . $e->getMessage());
    AdminTool::sendDiscordNotify($message);
    exit(1);
}

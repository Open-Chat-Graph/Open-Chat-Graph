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

        // open_chat_id => 各指標 を統合
        $merged = [];

        // GA4: ページビュー
        try {
            foreach ($client->fetchPageviews($date, $date) as $id => $pv) {
                $merged[$id]['pageviews'] = $pv;
            }
        } catch (\Throwable $e) {
            $errors[] = "GA4 {$date}: " . $e->getMessage();
        }

        // GSC: 検索クリック/表示/順位
        try {
            foreach ($client->fetchSearchAnalytics($date, $date) as $id => $s) {
                $merged[$id]['search_clicks'] = $s['clicks'];
                $merged[$id]['search_impressions'] = $s['impressions'];
                $merged[$id]['search_position'] = $s['position'];
            }
        } catch (\Throwable $e) {
            $errors[] = "GSC {$date}: " . $e->getMessage();
        }

        foreach ($merged as $id => $row) {
            DB::execute(
                "INSERT INTO alpha_room_access_daily
                    (open_chat_id, `date`, pageviews, search_clicks, search_impressions, search_position)
                 VALUES (:id, :date, :pv, :clicks, :impr, :pos)
                 ON DUPLICATE KEY UPDATE
                    pageviews = VALUES(pageviews),
                    search_clicks = VALUES(search_clicks),
                    search_impressions = VALUES(search_impressions),
                    search_position = VALUES(search_position)",
                [
                    'id' => (int)$id,
                    'date' => $date,
                    'pv' => (int)($row['pageviews'] ?? 0),
                    'clicks' => (int)($row['search_clicks'] ?? 0),
                    'impr' => (int)($row['search_impressions'] ?? 0),
                    'pos' => $row['search_position'] ?? null,
                ]
            );
            $totalUpserts++;
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

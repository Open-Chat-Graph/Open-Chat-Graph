<?php

declare(strict_types=1);

namespace App\Services\Alpha;

use App\Models\ApiRepositories\Alpha\AlphaAccessRankingRepository;
use App\Models\ApiRepositories\Alpha\AlphaGaSyncRepository;

/**
 * Alpha Labs: 部屋別アクセス/検索流入の日次同期サービス
 * （batch/exec/alpha_ga_sync.php から呼ばれる本体ロジック）。
 *
 * 本家 openchat-review.me の GA4(Data API) と Search Console から、
 * /openchat/{id} 詳細ページのアクセス・検索流入を取得し、open_chat_id 別に集計して
 * alpha_room_access_daily_ja に upsert(INSERT ... ON DUPLICATE KEY UPDATE)する
 * （SQL は AlphaGaSyncRepository に分離）。
 *
 * ja(urlRoot=='')専用。既定では直近数日（DEFAULT_DAYS_BACK）を毎回取り直して
 * 上書きする（GAは確定まで数日かかるため、直近を再取得して最終値に寄せる）。
 *
 * ▼ creds 未投入時の挙動:
 *   SecretsConfig の ga4PropertyId / gscSiteUrl / googleApiClientId / googleApiClientSecret /
 *   googleApiRefreshToken が1つでも欠けていれば、バッチ側が「何もせず exit 0」で
 *   安全に空振りする（本番を一切触らない。SecretsConfig::isGoogleAnalyticsConfigured() で判定）。
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
 */
class AlphaGaSyncService
{
    /** 毎回取り直す直近日数（GAの確定遅延に追従するため複数日を再取得して上書き） */
    public const DEFAULT_DAYS_BACK = 4;

    public function __construct(
        private AlphaGaClient $client,
        private AlphaGaSyncRepository $repo,
        private AlphaAccessRankingRepository $accessRankingRepo,
    ) {
    }

    /**
     * 日次同期の本体。
     *
     * 期間: $fromDate/$toDate（Y-m-d）が両方指定されればその期間、
     * そうでなければ前日を終端に $daysBack 日分（GA は当日分が未確定なので前日まで）。
     *
     * 期間まとめて取得してから、日付ごとに按分はせず「期間内合計を各日付に書く」のではなく、
     * 日次の粒度で保存するため日付ごとにAPIを叩く（GA4/GSC とも単日 dateRange で集計）。
     *
     * 部分エラー（特定日のAPI失敗）は errors に集約して続行する（全体は成功扱い）。
     * upsert 自体の失敗（DB障害）は例外のまま呼び出し元へ伝播する（現行バッチと同じ全体失敗）。
     *
     * @return array{startDate: string, endDate: string, upserts: int, errors: array<int, string>}
     */
    public function sync(int $daysBack = self::DEFAULT_DAYS_BACK, ?string $fromDate = null, ?string $toDate = null): array
    {
        if ($fromDate !== null && $toDate !== null) {
            $startDate = $fromDate;
            $endDate = $toDate;
        } else {
            // 前日を終端に N 日分（GA は当日分が未確定なので前日まで）
            $endDate = (new \DateTime('yesterday'))->format('Y-m-d');
            $startDate = (new \DateTime('yesterday'))->modify('-' . ($daysBack - 1) . ' day')->format('Y-m-d');
        }

        $period = new \DatePeriod(
            new \DateTime($startDate),
            new \DateInterval('P1D'),
            (new \DateTime($endDate))->modify('+1 day')
        );

        $totalUpserts = 0;
        $errors = [];

        foreach ($period as $day) {
            $date = $day->format('Y-m-d');

            // ---- 1) 部屋別 (alpha_room_access_daily_ja) ----
            // open_chat_id => 各指標 を統合
            $merged = [];

            // GA4: PV/UU/平均エンゲージ秒（1リクエストで取得）
            try {
                foreach ($this->client->fetchRoomMetrics($date, $date) as $id => $m) {
                    $merged[$id]['pageviews'] = $m['pageviews'];
                    $merged[$id]['active_users'] = $m['activeUsers'];
                    $merged[$id]['engagement_seconds'] = $m['engagementSeconds'];
                }
            } catch (\Throwable $e) {
                $errors[] = "GA4 room {$date}: " . $e->getMessage();
            }

            // GA4: 参加リンク押下数（/oc/{id}/jump の click & line.me）
            try {
                foreach ($this->client->fetchJumpClicks($date, $date) as $id => $jc) {
                    $merged[$id]['jump_clicks'] = $jc;
                }
            } catch (\Throwable $e) {
                $errors[] = "GA4 jump {$date}: " . $e->getMessage();
            }

            // GA4: 参加リンク押下のうち Organic Search セッション由来の件数
            try {
                foreach ($this->client->fetchJumpClicksByChannel($date, $date) as $id => $jco) {
                    $merged[$id]['jump_clicks_organic'] = $jco;
                }
            } catch (\Throwable $e) {
                $errors[] = "GA4 jump-organic {$date}: " . $e->getMessage();
            }

            // GSC: 検索クリック/表示/順位
            try {
                foreach ($this->client->fetchSearchAnalytics($date, $date) as $id => $s) {
                    $merged[$id]['search_clicks'] = $s['clicks'];
                    $merged[$id]['search_impressions'] = $s['impressions'];
                    $merged[$id]['search_position'] = $s['position'];
                }
            } catch (\Throwable $e) {
                $errors[] = "GSC room {$date}: " . $e->getMessage();
            }

            $totalUpserts += $this->repo->upsertRoomDaily($date, $merged);

            // ---- 1b) 部屋別 流入検索クエリ (alpha_room_search_query_daily_ja) ----
            try {
                $totalUpserts += $this->repo->upsertRoomSearchQueries(
                    $date,
                    $this->client->fetchRoomSearchQueries($date, $date)
                );
            } catch (\Throwable $e) {
                $errors[] = "GSC room-query {$date}: " . $e->getMessage();
            }

            // ---- 1c) 部屋別 リファラ元 (alpha_room_referrer_daily_ja) ----
            try {
                $totalUpserts += $this->repo->upsertRoomReferrers(
                    $date,
                    $this->client->fetchRoomReferrers($date, $date)
                );
            } catch (\Throwable $e) {
                $errors[] = "GA4 room-referrer {$date}: " . $e->getMessage();
            }

            // ---- 2) 非部屋ページ (alpha_page_access_daily_ja): トップ / おすすめ ----
            $pages = [];

            try {
                foreach ($this->client->fetchPageMetrics($date, $date) as $path => $m) {
                    $pages[$path]['label'] = $m['label'];
                    $pages[$path]['pageviews'] = $m['pageviews'];
                    $pages[$path]['active_users'] = $m['activeUsers'];
                }
            } catch (\Throwable $e) {
                $errors[] = "GA4 page {$date}: " . $e->getMessage();
            }

            try {
                foreach ($this->client->fetchPageSearchAnalytics($date, $date) as $path => $s) {
                    $pages[$path]['search_clicks'] = $s['clicks'];
                    $pages[$path]['search_impressions'] = $s['impressions'];
                    $pages[$path]['search_position'] = $s['position'];
                }
            } catch (\Throwable $e) {
                $errors[] = "GSC page {$date}: " . $e->getMessage();
            }

            $totalUpserts += $this->repo->upsertPageDaily($date, $pages);

            // ---- 3) 上位検索クエリ (alpha_search_query_daily_ja) ----
            try {
                $totalUpserts += $this->repo->upsertSearchQueries(
                    $date,
                    $this->client->fetchTopSearchQueries($date, $date)
                );
            } catch (\Throwable $e) {
                $errors[] = "GSC query {$date}: " . $e->getMessage();
            }

            // ---- 4) 非部屋ページ入室数の事前集計 (alpha_page_jump_daily_ja) ----
            // alpha_room_referrer_daily_ja / alpha_room_access_daily_ja 書込後に再計算して upsert。
            // GA不要・DBのみで完結（当日分のみなので高速）。
            try {
                $this->accessRankingRepo->rebuildPageJumpDaily($date);
            } catch (\Throwable $e) {
                $errors[] = "page-jump rebuild {$date}: " . $e->getMessage();
            }
        }

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'upserts' => $totalUpserts,
            'errors' => $errors,
        ];
    }
}

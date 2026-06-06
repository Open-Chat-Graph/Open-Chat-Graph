<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories\Alpha;

use App\Models\UserLogRepositories\UserLogDB;

/**
 * Alpha Labs GA/GSC 日次同期（AlphaGaSyncService）用リポジトリ。
 *
 * AlphaGaClient が取得した日次集計値を、日付×キーの upsert
 * (INSERT ... ON DUPLICATE KEY UPDATE) で各 alpha_xxx_daily_ja テーブルへ保存する。
 * 各メソッドは upsert した行数を返す（バッチの upserts 集計用）。
 *
 * 接続規約: αテーブル（alpha_xxx_ja）は userlog DB（UserLogDB）。多言語化時は _tw 等を増設
 * （詳細は ALPHA_SPEC.md「データ配置の契約」）。
 */
class AlphaGaSyncRepository
{
    /**
     * 部屋別 日次アクセス/検索流入 (alpha_room_access_daily_ja)。
     *
     * @param array<int|string, array<string, mixed>> $merged open_chat_id => 統合済み指標行
     */
    public function upsertRoomDaily(string $date, array $merged): int
    {
        UserLogDB::connect();

        $count = 0;
        foreach ($merged as $id => $row) {
            UserLogDB::execute(
                "INSERT INTO alpha_room_access_daily_ja
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
            $count++;
        }
        return $count;
    }

    /**
     * 部屋別 流入検索クエリ (alpha_room_search_query_daily_ja)。
     *
     * @param array<int|string, array<int, array<string, mixed>>> $queriesByRoom open_chat_id => クエリ行リスト
     */
    public function upsertRoomSearchQueries(string $date, array $queriesByRoom): int
    {
        UserLogDB::connect();

        $count = 0;
        foreach ($queriesByRoom as $id => $queries) {
            foreach ($queries as $q) {
                UserLogDB::execute(
                        "INSERT INTO alpha_room_search_query_daily_ja
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
                $count++;
            }
        }
        return $count;
    }

    /**
     * 部屋別 リファラ元 (alpha_room_referrer_daily_ja)。
     *
     * @param array<int|string, array<int, array<string, mixed>>> $referrersByRoom open_chat_id => リファラ行リスト
     */
    public function upsertRoomReferrers(string $date, array $referrersByRoom): int
    {
        UserLogDB::connect();

        $count = 0;
        foreach ($referrersByRoom as $id => $referrers) {
            foreach ($referrers as $r) {
                UserLogDB::execute(
                        "INSERT INTO alpha_room_referrer_daily_ja
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
                $count++;
            }
        }
        return $count;
    }

    /**
     * 非部屋ページ（トップ/おすすめ等）の日次アクセス (alpha_page_access_daily_ja)。
     *
     * @param array<string, array<string, mixed>> $pages path => 統合済み指標行
     */
    public function upsertPageDaily(string $date, array $pages): int
    {
        UserLogDB::connect();

        $count = 0;
        foreach ($pages as $path => $row) {
            UserLogDB::execute(
                "INSERT INTO alpha_page_access_daily_ja
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
            $count++;
        }
        return $count;
    }

    /**
     * サイト全体の上位検索クエリ (alpha_search_query_daily_ja)。
     *
     * @param array<int, array<string, mixed>> $queries クエリ行リスト
     */
    public function upsertSearchQueries(string $date, array $queries): int
    {
        UserLogDB::connect();

        $count = 0;
        foreach ($queries as $q) {
            UserLogDB::execute(
                    "INSERT INTO alpha_search_query_daily_ja
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
            $count++;
        }
        return $count;
    }
}

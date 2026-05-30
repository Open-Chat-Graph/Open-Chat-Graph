<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories\Alpha;

use App\Models\Repositories\DB;
use App\Models\UserLogRepositories\UserLogDB;

/**
 * Alpha 通知/アラート用リポジトリ。
 *
 * - ウォッチ設定（keyword/room/mylist閾値）の取得・保存
 * - 検出済み emid の重複防止記録
 * - 算出済み通知（alpha_notification）の保存・取得・既読更新
 *
 * すべて ocgraph_userlog DB（UserLogDB）に対して行う。
 * open_chat / statistics_ranking_hour 等の参照は本体 DB（DB）を使う。
 *
 * 追加のみ・既存破壊なし。ja（urlRoot==''）でのみ稼働する想定。
 */
class AlphaAlertRepository
{
    // ====================== ウォッチ設定: キーワード ======================

    /** @return array<int, array{id:int, keyword:string, category:?int, created_at:string}> */
    public function getKeywordWatches(string $userId): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT id, keyword, category, created_at
             FROM alpha_keyword_watch WHERE user_id = :uid ORDER BY id ASC",
            ['uid' => $userId]
        );
        return array_map(static fn($r) => [
            'id' => (int)$r['id'],
            'keyword' => $r['keyword'],
            'category' => $r['category'] === null ? null : (int)$r['category'],
            'created_at' => $r['created_at'],
        ], $rows);
    }

    /**
     * キーワードウォッチを丸ごと置き換える（PUT セマンティクス）。
     * 既存と一致するものは残し（id/seen を保つため）、無くなったものだけ削除、増えたものを追加。
     *
     * @param array<int, array{keyword:string, category:?int}> $watches
     */
    public function replaceKeywordWatches(string $userId, array $watches): void
    {
        $existing = $this->getKeywordWatches($userId);
        $existingMap = [];
        foreach ($existing as $e) {
            $existingMap[$this->keywordKey($e['keyword'], $e['category'])] = $e['id'];
        }

        $keepKeys = [];
        foreach ($watches as $w) {
            $kw = trim((string)($w['keyword'] ?? ''));
            if ($kw === '') {
                continue;
            }
            $cat = isset($w['category']) && $w['category'] !== null ? (int)$w['category'] : null;
            $key = $this->keywordKey($kw, $cat);
            $keepKeys[$key] = true;

            if (!isset($existingMap[$key])) {
                UserLogDB::execute(
                    "INSERT IGNORE INTO alpha_keyword_watch (user_id, keyword, category)
                     VALUES (:uid, :kw, :cat)",
                    ['uid' => $userId, 'kw' => $kw, 'cat' => $cat]
                );
            }
        }

        // 不要になったものを削除（seen も FK 無いので明示削除）
        foreach ($existing as $e) {
            $key = $this->keywordKey($e['keyword'], $e['category']);
            if (!isset($keepKeys[$key])) {
                UserLogDB::execute("DELETE FROM alpha_keyword_seen WHERE keyword_watch_id = :id", ['id' => $e['id']]);
                UserLogDB::execute("DELETE FROM alpha_keyword_watch WHERE id = :id", ['id' => $e['id']]);
            }
        }
    }

    private function keywordKey(string $keyword, ?int $category): string
    {
        return $keyword . "\x00" . ($category === null ? 'null' : (string)$category);
    }

    // ====================== ウォッチ設定: 部屋 ======================

    /** @return array<int, array{id:int, open_chat_id:int, up_member:?int, up_percent:?float, down_member:?int, down_percent:?float}> */
    public function getRoomWatches(string $userId): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT id, open_chat_id, up_member, up_percent, down_member, down_percent
             FROM alpha_room_watch WHERE user_id = :uid ORDER BY id ASC",
            ['uid' => $userId]
        );
        return array_map(static fn($r) => [
            'id' => (int)$r['id'],
            'open_chat_id' => (int)$r['open_chat_id'],
            'up_member' => $r['up_member'] === null ? null : (int)$r['up_member'],
            'up_percent' => $r['up_percent'] === null ? null : (float)$r['up_percent'],
            'down_member' => $r['down_member'] === null ? null : (int)$r['down_member'],
            'down_percent' => $r['down_percent'] === null ? null : (float)$r['down_percent'],
        ], $rows);
    }

    /**
     * 部屋ウォッチを丸ごと置き換える（PUT セマンティクス）。open_chat_id 単位で upsert/削除。
     *
     * @param array<int, array{open_chat_id:int, up_member:?int, up_percent:?float, down_member:?int, down_percent:?float}> $rooms
     */
    public function replaceRoomWatches(string $userId, array $rooms): void
    {
        $keepIds = [];
        foreach ($rooms as $r) {
            $ocId = (int)($r['open_chat_id'] ?? 0);
            if ($ocId < 1) {
                continue;
            }
            $keepIds[$ocId] = true;
            UserLogDB::execute(
                "INSERT INTO alpha_room_watch
                    (user_id, open_chat_id, up_member, up_percent, down_member, down_percent)
                 VALUES (:uid, :oc, :um, :up, :dm, :dp)
                 ON DUPLICATE KEY UPDATE
                    up_member = VALUES(up_member),
                    up_percent = VALUES(up_percent),
                    down_member = VALUES(down_member),
                    down_percent = VALUES(down_percent)",
                [
                    'uid' => $userId,
                    'oc' => $ocId,
                    'um' => $this->nullableInt($r['up_member'] ?? null),
                    'up' => $this->nullableFloat($r['up_percent'] ?? null),
                    'dm' => $this->nullableInt($r['down_member'] ?? null),
                    'dp' => $this->nullableFloat($r['down_percent'] ?? null),
                ]
            );
        }

        $existing = $this->getRoomWatches($userId);
        foreach ($existing as $e) {
            if (!isset($keepIds[$e['open_chat_id']])) {
                UserLogDB::execute("DELETE FROM alpha_room_watch WHERE id = :id", ['id' => $e['id']]);
            }
        }
    }

    // ====================== ウォッチ設定: マイリスト既定閾値 ======================

    /** @return array{up_percent:?float, down_percent:?float, enabled:bool} */
    public function getMylistThreshold(string $userId): array
    {
        $row = UserLogDB::fetch(
            "SELECT up_percent, down_percent, enabled FROM alpha_mylist_threshold WHERE user_id = :uid",
            ['uid' => $userId]
        );
        if (!$row) {
            return ['up_percent' => null, 'down_percent' => null, 'enabled' => false];
        }
        return [
            'up_percent' => $row['up_percent'] === null ? null : (float)$row['up_percent'],
            'down_percent' => $row['down_percent'] === null ? null : (float)$row['down_percent'],
            'enabled' => (bool)$row['enabled'],
        ];
    }

    public function saveMylistThreshold(string $userId, ?float $upPercent, ?float $downPercent, bool $enabled): void
    {
        UserLogDB::execute(
            "INSERT INTO alpha_mylist_threshold (user_id, up_percent, down_percent, enabled)
             VALUES (:uid, :up, :dp, :en)
             ON DUPLICATE KEY UPDATE up_percent = VALUES(up_percent),
                                     down_percent = VALUES(down_percent),
                                     enabled = VALUES(enabled),
                                     updated_at = current_timestamp()",
            ['uid' => $userId, 'up' => $upPercent, 'dp' => $downPercent, 'en' => $enabled ? 1 : 0]
        );
    }

    // ====================== 全ユーザー走査（cron用） ======================

    /** @return string[] ウォッチ設定を1件以上持つユーザーID */
    public function getAllUserIdsWithWatches(): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT user_id FROM alpha_keyword_watch
             UNION SELECT user_id FROM alpha_room_watch
             UNION SELECT user_id FROM alpha_mylist_threshold WHERE enabled = 1"
        );
        return array_map(static fn($r) => (string)$r['user_id'], $rows);
    }

    /**
     * 全ユーザーのキーワードウォッチを「キーワード(＋カテゴリ)のユニーク集合」に集約する。
     * cron で LINE公式APIをユニーク集合に対してまとめて叩くため。
     *
     * @return array<int, array{keyword:string, category:?int}>
     */
    public function getDistinctKeywords(): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT DISTINCT keyword, category FROM alpha_keyword_watch"
        );
        return array_map(static fn($r) => [
            'keyword' => (string)$r['keyword'],
            'category' => $r['category'] === null ? null : (int)$r['category'],
        ], $rows);
    }

    /** @return array<int, array{id:int, user_id:string, keyword:string, category:?int}> */
    public function getAllKeywordWatches(): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT id, user_id, keyword, category FROM alpha_keyword_watch ORDER BY id ASC"
        );
        return array_map(static fn($r) => [
            'id' => (int)$r['id'],
            'user_id' => (string)$r['user_id'],
            'keyword' => (string)$r['keyword'],
            'category' => $r['category'] === null ? null : (int)$r['category'],
        ], $rows);
    }

    /** @return array<int, array{user_id:string, open_chat_id:int, up_member:?int, up_percent:?float, down_member:?int, down_percent:?float}> */
    public function getAllRoomWatches(): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT user_id, open_chat_id, up_member, up_percent, down_member, down_percent
             FROM alpha_room_watch"
        );
        return array_map(static fn($r) => [
            'user_id' => (string)$r['user_id'],
            'open_chat_id' => (int)$r['open_chat_id'],
            'up_member' => $r['up_member'] === null ? null : (int)$r['up_member'],
            'up_percent' => $r['up_percent'] === null ? null : (float)$r['up_percent'],
            'down_member' => $r['down_member'] === null ? null : (int)$r['down_member'],
            'down_percent' => $r['down_percent'] === null ? null : (float)$r['down_percent'],
        ], $rows);
    }

    /** @return array<int, array{user_id:string, up_percent:?float, down_percent:?float}> enabled なものだけ */
    public function getAllEnabledMylistThresholds(): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT user_id, up_percent, down_percent FROM alpha_mylist_threshold WHERE enabled = 1"
        );
        return array_map(static fn($r) => [
            'user_id' => (string)$r['user_id'],
            'up_percent' => $r['up_percent'] === null ? null : (float)$r['up_percent'],
            'down_percent' => $r['down_percent'] === null ? null : (float)$r['down_percent'],
        ], $rows);
    }

    /**
     * ユーザーのマイリスト open_chat_id 配列を oc_list_user（サーバ側ミラー）から取得。
     * マイリストUI表示時に同期される JSON 配列。未同期ユーザーは空。
     *
     * @return int[]
     */
    public function getMylistOpenChatIds(string $userId): array
    {
        $row = UserLogDB::fetch(
            "SELECT oc_list FROM oc_list_user WHERE user_id = :uid",
            ['uid' => $userId]
        );
        if (!$row || empty($row['oc_list'])) {
            return [];
        }
        $ids = json_decode($row['oc_list'], true);
        if (!is_array($ids)) {
            return [];
        }
        return array_values(array_filter(array_map('intval', $ids), static fn($i) => $i > 0));
    }

    // ====================== キーワード検出: 重複防止 ======================

    /**
     * keyword_watch_id について既に検出済みの emid 集合を返す（emid => true）。
     *
     * @return array<string, bool>
     */
    public function getSeenEmids(int $keywordWatchId): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT emid FROM alpha_keyword_seen WHERE keyword_watch_id = :id",
            ['id' => $keywordWatchId]
        );
        $set = [];
        foreach ($rows as $r) {
            $set[(string)$r['emid']] = true;
        }
        return $set;
    }

    public function markEmidSeen(int $keywordWatchId, string $emid): void
    {
        UserLogDB::execute(
            "INSERT IGNORE INTO alpha_keyword_seen (keyword_watch_id, emid) VALUES (:id, :emid)",
            ['id' => $keywordWatchId, 'emid' => $emid]
        );
    }

    // ====================== 通知アイテム ======================

    /**
     * 通知を保存（dedup_key により重複は無視）。
     * @return bool 新規に保存されたら true
     */
    public function insertNotification(string $userId, string $type, array $payload, string $dedupKey): bool
    {
        $stmt = UserLogDB::execute(
            "INSERT IGNORE INTO alpha_notification (user_id, type, payload, dedup_key)
             VALUES (:uid, :type, :payload, :dedup)",
            [
                'uid' => $userId,
                'type' => $type,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'dedup' => $dedupKey,
            ]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * ユーザーの通知一覧（新しい順）。
     *
     * @return array<int, array{id:int, type:string, payload:array, is_read:bool, created_at:string}>
     */
    public function getNotifications(string $userId, int $limit = 100): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT id, type, payload, is_read, created_at
             FROM alpha_notification WHERE user_id = :uid
             ORDER BY id DESC LIMIT :lim",
            ['uid' => $userId, 'lim' => $limit]
        );
        return array_map(static function ($r) {
            $payload = json_decode($r['payload'], true);
            return [
                'id' => (int)$r['id'],
                'type' => (string)$r['type'],
                'payload' => is_array($payload) ? $payload : [],
                'is_read' => (bool)$r['is_read'],
                'created_at' => (string)$r['created_at'],
            ];
        }, $rows);
    }

    public function markAllRead(string $userId): void
    {
        UserLogDB::execute(
            "UPDATE alpha_notification SET is_read = 1 WHERE user_id = :uid AND is_read = 0",
            ['uid' => $userId]
        );
    }

    public function markRead(string $userId, array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn($i) => $i > 0));
        if (empty($ids)) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        UserLogDB::execute(
            "UPDATE alpha_notification SET is_read = 1 WHERE user_id = ? AND id IN ({$placeholders})",
            array_merge([$userId], $ids)
        );
    }

    // ====================== 本体DB参照（movement用ヘルパ） ======================

    /**
     * open_chat の現在値（name/member/category/url/img/emblem）を id 配列で取得。
     *
     * @param int[] $ids
     * @return array<int, array<string, mixed>> id => row
     */
    public function getOpenChatMap(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn($i) => $i > 0));
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        DB::connect();
        $rows = DB::fetchAll(
            "SELECT id, name, member, category, description, img_url, emblem, url, join_method_type
             FROM open_chat WHERE id IN ({$placeholders})",
            $ids
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['id']] = $r;
        }
        return $map;
    }

    /**
     * statistics_ranking_hour（毎時の対前回差分）を id 配列で取得。
     * 直近の毎時クロールで member 反映済みの部屋のみ行が存在する。
     *
     * @param int[] $ids
     * @return array<int, array{diff_member:int, percent_increase:float}> open_chat_id => diff
     */
    public function getHourlyDiffMap(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn($i) => $i > 0));
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        DB::connect();
        $rows = DB::fetchAll(
            "SELECT open_chat_id, diff_member, percent_increase
             FROM statistics_ranking_hour WHERE open_chat_id IN ({$placeholders})",
            $ids
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['open_chat_id']] = [
                'diff_member' => (int)$r['diff_member'],
                'percent_increase' => (float)$r['percent_increase'],
            ];
        }
        return $map;
    }

    /**
     * emid 配列のうち open_chat に既登録のものを emid => id で返す。
     * （キーワード検出で「新規（未登録）」を判定するため）
     *
     * @param string[] $emids
     * @return array<string, int>
     */
    public function getRegisteredEmidMap(array $emids): array
    {
        $emids = array_values(array_filter($emids, static fn($e) => $e !== ''));
        if (empty($emids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($emids), '?'));
        DB::connect();
        $rows = DB::fetchAll(
            "SELECT id, emid FROM open_chat WHERE emid IN ({$placeholders})",
            $emids
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(string)$r['emid']] = (int)$r['id'];
        }
        return $map;
    }

    private function nullableInt(mixed $v): ?int
    {
        return ($v === null || $v === '') ? null : (int)$v;
    }

    private function nullableFloat(mixed $v): ?float
    {
        return ($v === null || $v === '') ? null : (float)$v;
    }
}

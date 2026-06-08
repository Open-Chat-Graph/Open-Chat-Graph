<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories\Alpha;

use App\Models\Repositories\DB;
use App\Models\SQLite\SQLiteRankingPosition;
use App\Models\UserLogRepositories\UserLogDB;

/**
 * Alpha 通知/アラート用リポジトリ。
 *
 * - ウォッチ設定（keyword/room/mylist閾値）の取得・保存
 * - 検出済み emid の重複防止記録
 * - 算出済み通知（alpha_notification_ja）の保存・取得・既読更新
 *
 * 接続規約: αテーブル（alpha_xxx_ja）は userlog DB（UserLogDB）。多言語化時は _tw 等を増設。
 * oc_list_user（マイリスト本体・サフィックス無し）も userlog（UserLogDB）。
 * open_chat / statistics_ranking_hour 等の ocreview 単独参照は従来どおり DB を使う。
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
             FROM alpha_keyword_watch_ja WHERE user_id = :uid ORDER BY id ASC",
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
                    "INSERT IGNORE INTO alpha_keyword_watch_ja (user_id, keyword, category)
                     VALUES (:uid, :kw, :cat)",
                    ['uid' => $userId, 'kw' => $kw, 'cat' => $cat]
                );
            }
        }

        // 不要になったものを削除（seen も FK 無いので明示削除）
        foreach ($existing as $e) {
            $key = $this->keywordKey($e['keyword'], $e['category']);
            if (!isset($keepKeys[$key])) {
                UserLogDB::execute("DELETE FROM alpha_keyword_seen_ja WHERE keyword_watch_id = :id", ['id' => $e['id']]);
                UserLogDB::execute("DELETE FROM alpha_keyword_watch_ja WHERE id = :id", ['id' => $e['id']]);
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
             FROM alpha_room_watch_ja WHERE user_id = :uid ORDER BY id ASC",
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
                "INSERT INTO alpha_room_watch_ja
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
                UserLogDB::execute("DELETE FROM alpha_room_watch_ja WHERE id = :id", ['id' => $e['id']]);
            }
        }
    }

    // ====================== ウォッチ設定: マイリスト変動閾値 ======================

    /** @return array{up_percent:?float, down_percent:?float, up_member:?int, down_member:?int, scope:string, target_oc_ids:?array<int,int>, enabled:bool} */
    public function getMylistThreshold(string $userId): array
    {
        $row = UserLogDB::fetch(
            "SELECT up_percent, down_percent, up_member, down_member, scope, target_oc_ids, enabled
             FROM alpha_mylist_threshold_ja WHERE user_id = :uid",
            ['uid' => $userId]
        );
        if (!$row) {
            return [
                'up_percent' => null,
                'down_percent' => null,
                'up_member' => null,
                'down_member' => null,
                'scope' => 'all',
                'target_oc_ids' => null,
                'enabled' => false,
            ];
        }
        return [
            'up_percent' => $row['up_percent'] === null ? null : (float)$row['up_percent'],
            'down_percent' => $row['down_percent'] === null ? null : (float)$row['down_percent'],
            'up_member' => $row['up_member'] === null ? null : (int)$row['up_member'],
            'down_member' => $row['down_member'] === null ? null : (int)$row['down_member'],
            'scope' => $this->normalizeScope($row['scope'] ?? 'all'),
            'target_oc_ids' => $this->decodeOcIds($row['target_oc_ids'] ?? null),
            'enabled' => (bool)$row['enabled'],
        ];
    }

    /**
     * @param int[]|null $targetOcIds scope='all' は null（全体＝oc_list_user フォールバック）、
     *                                それ以外はフロントが解決した対象 open_chat_id の集合。
     */
    public function saveMylistThreshold(
        string $userId,
        ?float $upPercent,
        ?float $downPercent,
        bool $enabled,
        ?int $upMember = null,
        ?int $downMember = null,
        string $scope = 'all',
        ?array $targetOcIds = null,
    ): void {
        $scope = $this->normalizeScope($scope);
        $idsJson = $targetOcIds === null
            ? null
            : json_encode(array_values(array_filter(
                array_map('intval', $targetOcIds),
                static fn($i) => $i > 0
            )));

        UserLogDB::execute(
            "INSERT INTO alpha_mylist_threshold_ja
                (user_id, up_percent, down_percent, up_member, down_member, scope, target_oc_ids, enabled)
             VALUES (:uid, :up, :dp, :um, :dm, :scope, :ids, :en)
             ON DUPLICATE KEY UPDATE up_percent = VALUES(up_percent),
                                     down_percent = VALUES(down_percent),
                                     up_member = VALUES(up_member),
                                     down_member = VALUES(down_member),
                                     scope = VALUES(scope),
                                     target_oc_ids = VALUES(target_oc_ids),
                                     enabled = VALUES(enabled),
                                     updated_at = current_timestamp()",
            [
                'uid' => $userId,
                'up' => $upPercent,
                'dp' => $downPercent,
                'um' => $upMember,
                'dm' => $downMember,
                'scope' => $scope,
                'ids' => $idsJson,
                'en' => $enabled ? 1 : 0,
            ]
        );
    }

    /** scope を既知の値に丸める（不明値は 'all'）。 */
    private function normalizeScope(mixed $scope): string
    {
        $s = (string)$scope;
        return in_array($s, ['all', 'root', 'folder'], true) ? $s : 'all';
    }

    /** target_oc_ids JSON を int 配列に。空/不正は null（＝全体フォールバック）。 */
    private function decodeOcIds(mixed $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }
        $arr = json_decode((string)$json, true);
        if (!is_array($arr)) {
            return null;
        }
        return array_values(array_filter(array_map('intval', $arr), static fn($i) => $i > 0));
    }

    // ====================== 全ユーザー走査（cron用） ======================

    /** @return string[] ウォッチ設定を1件以上持つユーザーID */
    public function getAllUserIdsWithWatches(): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT user_id FROM alpha_keyword_watch_ja
             UNION SELECT user_id FROM alpha_room_watch_ja
             UNION SELECT user_id FROM alpha_mylist_threshold_ja WHERE enabled = 1"
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
            "SELECT DISTINCT keyword, category FROM alpha_keyword_watch_ja"
        );
        return array_map(static fn($r) => [
            'keyword' => (string)$r['keyword'],
            'category' => $r['category'] === null ? null : (int)$r['category'],
        ], $rows);
    }

    /**
     * 非凍結の購読（frozen=0）を持つユーザーのウォッチだけを返す。
     * 購読が無い／全端末凍結中のユーザーは毎時の LINE 検索処理から外れる（コスト上限化）。
     * 行自体は消さない（後述の deleteOrphanKeywordWatches が担当）。
     *
     * @return array<int, array{id:int, user_id:string, keyword:string, category:?int, created_at:string}>
     */
    public function getAllKeywordWatches(): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT id, user_id, keyword, category, created_at
             FROM alpha_keyword_watch_ja
             WHERE user_id IN (SELECT user_id FROM alpha_push_subscription_ja WHERE frozen = 0 AND user_id IS NOT NULL)
             ORDER BY id ASC"
        );
        return array_map(static fn($r) => [
            'id' => (int)$r['id'],
            'user_id' => (string)$r['user_id'],
            'keyword' => (string)$r['keyword'],
            'category' => $r['category'] === null ? null : (int)$r['category'],
            'created_at' => (string)$r['created_at'],
        ], $rows);
    }

    /**
     * 購読行が1行も無いユーザー（frozen含め完全 Gone）の watch を削除する。
     * 凍結中ユーザーは購読行が残っているため削除されない（凍結は復帰可能）。
     * 親 watch が消えて孤児になった alpha_keyword_seen_ja 行も合わせて掃除する。
     *
     * @return int 削除した keyword_watch 件数
     */
    public function deleteOrphanKeywordWatches(): int
    {
        // 孤児 seen を先に掃除（watch 削除後に孤児になった行を拾う）
        UserLogDB::execute(
            "DELETE FROM alpha_keyword_seen_ja
             WHERE keyword_watch_id NOT IN (
                 SELECT id FROM alpha_keyword_watch_ja
                 WHERE user_id NOT IN (SELECT user_id FROM alpha_push_subscription_ja WHERE user_id IS NOT NULL)
             )"
        );

        // 購読が1行も無いユーザーの watch を削除
        $stmt = UserLogDB::execute(
            "DELETE FROM alpha_keyword_watch_ja
             WHERE user_id NOT IN (SELECT user_id FROM alpha_push_subscription_ja WHERE user_id IS NOT NULL)"
        );

        // 削除後に残った孤児 seen（上の seen 掃除で捕捉できなかった場合の安全網）
        UserLogDB::execute(
            "DELETE FROM alpha_keyword_seen_ja
             WHERE keyword_watch_id NOT IN (SELECT id FROM alpha_keyword_watch_ja)"
        );

        return $stmt->rowCount();
    }

    /** @return array<int, array{user_id:string, open_chat_id:int, up_member:?int, up_percent:?float, down_member:?int, down_percent:?float}> */
    public function getAllRoomWatches(): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT user_id, open_chat_id, up_member, up_percent, down_member, down_percent
             FROM alpha_room_watch_ja"
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

    /** @return array<int, array{user_id:string, up_percent:?float, down_percent:?float, up_member:?int, down_member:?int, scope:string, target_oc_ids:?array<int,int>}> enabled なものだけ */
    public function getAllEnabledMylistThresholds(): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT user_id, up_percent, down_percent, up_member, down_member, scope, target_oc_ids
             FROM alpha_mylist_threshold_ja WHERE enabled = 1"
        );
        return array_map(fn($r) => [
            'user_id' => (string)$r['user_id'],
            'up_percent' => $r['up_percent'] === null ? null : (float)$r['up_percent'],
            'down_percent' => $r['down_percent'] === null ? null : (float)$r['down_percent'],
            'up_member' => $r['up_member'] === null ? null : (int)$r['up_member'],
            'down_member' => $r['down_member'] === null ? null : (int)$r['down_member'],
            'scope' => $this->normalizeScope($r['scope'] ?? 'all'),
            'target_oc_ids' => $this->decodeOcIds($r['target_oc_ids'] ?? null),
        ], $rows);
    }

    /**
     * ユーザーのマイリスト open_chat_id 配列を oc_list_user（サーバ側ミラー）から取得。
     * マイリストUI表示時に同期される JSON 配列。未同期ユーザーは空。
     *
     * oc_list_user は userlog 側（マイリスト本体）に残留するため UserLogDB を使う。
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

    // ====================== 未登録部屋プール (alpha_search_seen_room_ja) ======================

    /**
     * 検索に出た「未登録部屋」を共有プールに upsert する。
     *
     * 既存行があれば name/member/last_seen_at を更新し、keywords に $keyword を
     * （未収録なら）カンマ連結で追加する。新規行は first_seen_at = now。
     *
     * keywords はユーザー横断で「この部屋を見つけたアラートキーワードの集合」。
     * 配信時に「K が keywords のいずれかに完全一致」で判定する。
     *
     * @param string  $emid    LINE square の emid（未登録）
     * @param string  $name    部屋名
     * @param int|null $member 人数（squareには無いので基本 null）
     * @param string  $keyword この部屋を見つけたアラートキーワード
     */
    public function upsertSeenRoom(string $emid, string $name, ?int $member, string $keyword): void
    {
        if ($emid === '') {
            return;
        }

        // keywords はカンマ区切り集合。keyword 自体のカンマは FIND_IN_SET を壊すので除去。
        $keyword = $this->normalizeKeyword($keyword);

        $row = UserLogDB::fetch(
            "SELECT keywords FROM alpha_search_seen_room_ja WHERE emid = :emid",
            ['emid' => $emid]
        );

        if ($row) {
            // 既存: keywords に keyword を（未収録なら）足し、name/member/last_seen を更新
            $keywords = $this->mergeKeyword((string)$row['keywords'], $keyword);
            UserLogDB::execute(
                "UPDATE alpha_search_seen_room_ja
                 SET name = :name, member = :member, keywords = :kw, last_seen_at = current_timestamp()
                 WHERE emid = :emid",
                [
                    'name' => $this->truncate($name, 190),
                    'member' => $member,
                    'kw' => $keywords,
                    'emid' => $emid,
                ]
            );
        } else {
            UserLogDB::execute(
                "INSERT INTO alpha_search_seen_room_ja (emid, name, member, keywords)
                 VALUES (:emid, :name, :member, :kw)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    member = VALUES(member),
                    keywords = VALUES(keywords),
                    last_seen_at = current_timestamp()",
                [
                    'emid' => $emid,
                    'name' => $this->truncate($name, 190),
                    'member' => $member,
                    'kw' => $keyword,
                ]
            );
        }
    }

    /**
     * あるキーワード K にヒットする未登録部屋のうち、配信条件を満たすものを返す。
     *
     * 条件:
     *   - K が keywords（カンマ集合）のいずれかに完全一致
     *   - first_seen_at >= :createdAt（このウォッチ登録時刻以降に初めて見つかった部屋）
     *
     * 重複排除（そのユーザーに未配信か）は呼び出し側で alpha_keyword_seen_ja を使って行う。
     *
     * @return array<int, array{emid:string, name:string, member:?int, first_seen_at:string}>
     */
    public function getDeliverableSeenRooms(string $keyword, string $createdAt): array
    {
        // upsert 時と同じ正規化（カンマ除去）で照合する。
        $keyword = $this->normalizeKeyword($keyword);

        // keywords は「a,b,c」形式。完全一致のため FIND_IN_SET を使う。
        $rows = UserLogDB::fetchAll(
            "SELECT emid, name, member, first_seen_at
             FROM alpha_search_seen_room_ja
             WHERE first_seen_at >= :created
               AND FIND_IN_SET(:kw, keywords)
             ORDER BY first_seen_at ASC",
            ['created' => $createdAt, 'kw' => $keyword]
        );
        return array_map(static fn($r) => [
            'emid' => (string)$r['emid'],
            'name' => (string)($r['name'] ?? ''),
            'member' => $r['member'] === null ? null : (int)$r['member'],
            'first_seen_at' => (string)$r['first_seen_at'],
        ], $rows);
    }

    /**
     * keywords のカンマ集合に keyword を（未収録なら）追加して返す。
     * keyword 自体にカンマを含む場合は FIND_IN_SET が壊れるためカンマを除去して正規化する。
     */
    private function mergeKeyword(string $keywords, string $keyword): string
    {
        $keyword = $this->normalizeKeyword($keyword);
        $set = array_values(array_filter(
            array_map('trim', explode(',', $keywords)),
            static fn($k) => $k !== ''
        ));
        if (!in_array($keyword, $set, true)) {
            $set[] = $keyword;
        }
        return implode(',', $set);
    }

    /** keywords 集合に入れる前のキーワード正規化（カンマ除去・トリム）。 */
    private function normalizeKeyword(string $keyword): string
    {
        return trim(str_replace(',', ' ', $keyword));
    }

    private function truncate(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) : $s;
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
            "SELECT emid FROM alpha_keyword_seen_ja WHERE keyword_watch_id = :id",
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
            "INSERT IGNORE INTO alpha_keyword_seen_ja (keyword_watch_id, emid) VALUES (:id, :emid)",
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
            "INSERT IGNORE INTO alpha_notification_ja (user_id, type, payload, dedup_key)
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
             FROM alpha_notification_ja WHERE user_id = :uid
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

    /**
     * この毎時(hourBucket)に部屋単体アラート(type='room')で通知を出した
     * (user_id, open_chat_id) を返す。マイリスト変動との二重通知回避に使う。
     *
     * dedup_key は 'room:{open_chat_id}:{direction}:{hourBucket}' 形式。
     * hourBucket 接尾で絞り、key から open_chat_id を取り出す。
     *
     * @return array<int, array{user_id:string, open_chat_id:int}>
     */
    public function getRoomNotificationKeys(string $hourBucket): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT user_id, dedup_key FROM alpha_notification_ja
             WHERE type = 'room' AND dedup_key LIKE :pat",
            ['pat' => 'room:%:' . $hourBucket]
        );
        $out = [];
        foreach ($rows as $r) {
            // 'room:{ocId}:{direction}:{hourBucket}'
            $parts = explode(':', (string)$r['dedup_key']);
            if (count($parts) < 4 || $parts[0] !== 'room') {
                continue;
            }
            $ocId = (int)$parts[1];
            if ($ocId < 1) {
                continue;
            }
            $out[] = ['user_id' => (string)$r['user_id'], 'open_chat_id' => $ocId];
        }
        return $out;
    }

    /**
     * この毎時(hourBucket)にスマートフォルダ変動(type='folder')で通知を出した
     * (user_id, open_chat_id) を返す。マイリスト変動との二重通知回避
     * （優先順位 room > folder > mylist）に使う。
     *
     * dedup_key は 'fm:{folder_id}:{open_chat_id}:{direction}:{hourBucket}' 形式。
     * folder_id はフロント生成の文字列で ':' を含み得るため、末尾側から取り出す。
     *
     * @return array<int, array{user_id:string, open_chat_id:int}>
     */
    public function getFolderNotificationKeys(string $hourBucket): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT user_id, dedup_key FROM alpha_notification_ja
             WHERE type = 'folder' AND dedup_key LIKE :pat",
            ['pat' => 'fm:%:' . $hourBucket]
        );
        $out = [];
        foreach ($rows as $r) {
            // 'fm:{folderId}:{ocId}:{direction}:{hourBucket}'（folderId に ':' があり得るので末尾から数える）
            $parts = explode(':', (string)$r['dedup_key']);
            $n = count($parts);
            if ($n < 5 || $parts[0] !== 'fm') {
                continue;
            }
            $ocId = (int)$parts[$n - 3];
            if ($ocId < 1) {
                continue;
            }
            $out[] = ['user_id' => (string)$r['user_id'], 'open_chat_id' => $ocId];
        }
        return $out;
    }

    public function markAllRead(string $userId): void
    {
        UserLogDB::execute(
            "UPDATE alpha_notification_ja SET is_read = 1 WHERE user_id = :uid AND is_read = 0",
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
            "UPDATE alpha_notification_ja SET is_read = 1 WHERE user_id = ? AND id IN ({$placeholders})",
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
            "SELECT id, name, member, category, description, img_url, emblem, url, join_method_type, created_at
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

    // ====================== SQLite参照（rank_jump 検知用） ======================

    /**
     * SQLite ranking_position（日次の公式ランキング順位）から、指定部屋の直近観測を取得する。
     *
     * ranking テーブルは dailyTask が1日1回まとめて書く日次データで、
     * 1部屋につき category=0（全体）と部屋自身のカテゴリの行が日ごとに入る
     * （掲載されていない日・カテゴリは行が無い）。
     * 読み方（category=0=全体ランキング）は AlphaInsightsService の position_trend と同じ。
     *
     * 返り値:
     *   - lastDate: ランキングデータ全体の最新スナップショット日（total_count の最大 time の日付）。
     *               「現在掲載されているか」のフレッシュネス判定に使う。取れなければ null。
     *   - rows:     (open_chat_id, category, date DESC) 順の観測行。lookback 日以内のみ。
     *
     * @param int[] $ocIds
     * @return array{
     *     lastDate: ?string,
     *     rows: array<int, array{open_chat_id:int, category:int, position:int, time:string, date:string}>
     * }
     */
    public function getRecentRankingObservations(array $ocIds, int $lookbackDays = 14): array
    {
        $ocIds = array_values(array_filter(array_map('intval', $ocIds), static fn($i) => $i > 0));
        if (empty($ocIds)) {
            return ['lastDate' => null, 'rows' => []];
        }

        $placeholders = implode(',', array_fill(0, count($ocIds), '?'));
        $since = date('Y-m-d', strtotime('-' . max(1, $lookbackDays) . ' days'));

        SQLiteRankingPosition::connect(['mode' => '?mode=ro']);

        // 最新スナップショット日（total_count は1日×カテゴリにつき1行の小さなテーブル）
        $lastDate = SQLiteRankingPosition::fetchColumn("SELECT DATE(MAX(time)) FROM total_count");

        $rows = SQLiteRankingPosition::fetchAll(
            "SELECT open_chat_id, category, position, time, date
             FROM ranking
             WHERE open_chat_id IN ({$placeholders}) AND date >= ?
             ORDER BY open_chat_id ASC, category ASC, date DESC",
            array_merge($ocIds, [$since])
        );

        SQLiteRankingPosition::$pdo = null;

        return [
            'lastDate' => $lastDate !== false && $lastDate !== null ? (string)$lastDate : null,
            'rows' => array_map(static fn($r) => [
                'open_chat_id' => (int)$r['open_chat_id'],
                'category' => (int)$r['category'],
                'position' => (int)$r['position'],
                'time' => (string)$r['time'],
                'date' => (string)$r['date'],
            ], $rows),
        ];
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

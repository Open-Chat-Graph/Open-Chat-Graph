<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories\Alpha;

use App\Models\UserLogRepositories\UserLogDB;

/**
 * Alpha Web Push 購読リポジトリ（alpha_push_subscription_ja）。
 *
 * ペイロード無し tickle 方式: 購読の endpoint / p256dh / auth を保存し、
 * 毎時バッチが新規通知のあったユーザーの購読へ空POST（VAPID付き）を送る。
 *
 * - endpoint は長い（FCM等で~400字）ため UNIQUE は SHA-256 ハッシュ列（endpoint_hash）。
 * - 同一 endpoint の再購読は upsert（user_id / 鍵を更新し fail_count・凍結をリセット）。
 * - 404/410（購読が Gone）は即削除。一過性失敗（5xx/429/タイムアウト等）は fail_count を
 *   加算し first_fail_at を記録するのみ（削除しない）。3日連続失敗で frozen=1 に凍結する
 *   （freezeStaleSubscriptions で一括更新）。凍結中は送信対象外。成功・再購読で解凍。
 *
 * 接続規約: αテーブル（alpha_xxx_ja）は userlog DB（UserLogDB）。ja のみ稼働。
 */
class AlphaPushRepository
{
    /** 同一 endpoint は1行に upsert（user_id・鍵更新、fail_count・凍結リセット）。 */
    public function upsertSubscription(string $userId, string $endpoint, string $p256dh, string $auth): void
    {
        UserLogDB::execute(
            "INSERT INTO alpha_push_subscription_ja (user_id, endpoint, endpoint_hash, p256dh, auth)
             VALUES (:uid, :ep, :hash, :p256dh, :auth)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                endpoint = VALUES(endpoint),
                p256dh = VALUES(p256dh),
                auth = VALUES(auth),
                fail_count = 0,
                frozen = 0,
                first_fail_at = NULL",
            [
                'uid' => $userId,
                'ep' => $endpoint,
                'hash' => hash('sha256', $endpoint),
                'p256dh' => $p256dh,
                'auth' => $auth,
            ]
        );
    }

    public function deleteByEndpoint(string $endpoint): void
    {
        UserLogDB::execute(
            "DELETE FROM alpha_push_subscription_ja WHERE endpoint_hash = :hash",
            ['hash' => hash('sha256', $endpoint)]
        );
    }

    public function deleteById(int $id): void
    {
        UserLogDB::execute(
            "DELETE FROM alpha_push_subscription_ja WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * 対象ユーザー群の全購読を取得（送信バッチ用）。凍結中（frozen=1）は除外。
     *
     * @param string[] $userIds
     * @return array<int, array{id:int, user_id:string, endpoint:string, p256dh:string, auth:string, fail_count:int}>
     */
    public function getSubscriptionsByUserIds(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(
            array_map('strval', $userIds),
            static fn($u) => $u !== ''
        )));
        if (empty($userIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $rows = UserLogDB::fetchAll(
            "SELECT id, user_id, endpoint, p256dh, auth, fail_count
             FROM alpha_push_subscription_ja WHERE user_id IN ({$placeholders}) AND frozen = 0",
            $userIds
        );
        return array_map(static fn($r) => [
            'id' => (int)$r['id'],
            'user_id' => (string)$r['user_id'],
            'endpoint' => (string)$r['endpoint'],
            'p256dh' => (string)$r['p256dh'],
            'auth' => (string)$r['auth'],
            'fail_count' => (int)$r['fail_count'],
        ], $rows);
    }

    /** 送信成功: last_sent_at を更新し fail_count・凍結をリセット。 */
    public function markSent(int $id): void
    {
        UserLogDB::execute(
            "UPDATE alpha_push_subscription_ja
             SET last_sent_at = current_timestamp(), fail_count = 0, frozen = 0, first_fail_at = NULL
             WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * 送信失敗（一過性）: fail_count を加算し、first_fail_at が NULL のときのみ現在時刻を記録する。
     * 購読は削除しない。3日後に freezeStaleSubscriptions() で凍結する。
     */
    public function incrementFail(int $id): void
    {
        UserLogDB::execute(
            "UPDATE alpha_push_subscription_ja
             SET fail_count = fail_count + 1,
                 first_fail_at = COALESCE(first_fail_at, current_timestamp())
             WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * frozen=0（有効）な購読が1件以上あれば true。
     * キーワードウォッチ保存前の購読存在チェックに使う。
     */
    public function hasActivePushSubscription(string $userId): bool
    {
        $row = UserLogDB::fetch(
            "SELECT EXISTS(SELECT 1 FROM alpha_push_subscription_ja WHERE user_id = :uid AND frozen = 0) AS ex",
            ['uid' => $userId]
        );
        return $row !== false && (bool)$row['ex'];
    }

    /**
     * 3日以上連続失敗している購読を凍結する（frozen=1）。
     * 毎時バッチから呼び出す想定（Phase 2 で配線予定）。
     *
     * @return int 凍結した行数
     */
    public function freezeStaleSubscriptions(): int
    {
        $stmt = UserLogDB::execute(
            "UPDATE alpha_push_subscription_ja
             SET frozen = 1
             WHERE frozen = 0
               AND first_fail_at IS NOT NULL
               AND first_fail_at < (current_timestamp() - INTERVAL 3 DAY)"
        );
        return $stmt->rowCount();
    }
}

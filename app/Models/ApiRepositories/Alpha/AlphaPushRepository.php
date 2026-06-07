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
 * - 同一 endpoint の再購読は upsert（user_id / 鍵を更新し fail_count をリセット）。
 * - 送信失敗が累積（fail_count >= 5）した購読は削除する。404/410 は即削除。
 *
 * 接続規約: αテーブル（alpha_xxx_ja）は userlog DB（UserLogDB）。ja のみ稼働。
 */
class AlphaPushRepository
{
    /** 連続失敗がこの回数に達した購読は無効とみなし削除する */
    public const MAX_FAIL_COUNT = 5;

    /** 同一 endpoint は1行に upsert（user_id・鍵更新、fail_count リセット）。 */
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
                fail_count = 0",
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
     * 対象ユーザー群の全購読を取得（送信バッチ用）。
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
             FROM alpha_push_subscription_ja WHERE user_id IN ({$placeholders})",
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

    /** 送信成功: last_sent_at を更新し fail_count をリセット。 */
    public function markSent(int $id): void
    {
        UserLogDB::execute(
            "UPDATE alpha_push_subscription_ja
             SET last_sent_at = current_timestamp(), fail_count = 0
             WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * 送信失敗: fail_count を加算し、MAX_FAIL_COUNT に達したら購読を削除する。
     *
     * @return bool 削除した場合 true
     */
    public function incrementFail(int $id): bool
    {
        UserLogDB::execute(
            "UPDATE alpha_push_subscription_ja SET fail_count = fail_count + 1 WHERE id = :id",
            ['id' => $id]
        );
        $row = UserLogDB::fetch(
            "SELECT fail_count FROM alpha_push_subscription_ja WHERE id = :id",
            ['id' => $id]
        );
        if ($row && (int)$row['fail_count'] >= self::MAX_FAIL_COUNT) {
            $this->deleteById($id);
            return true;
        }
        return false;
    }
}

<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories\Alpha;

use App\Models\UserLogRepositories\UserLogDB;

/**
 * α マイリスト（フォルダ構造）サーバ保存用リポジトリ。
 *
 * - フォルダ定義: alpha_mylist_folder_ja
 * - アイテム: alpha_mylist_item_ja
 *
 * 接続: userlog DB（UserLogDB）。ja（urlRoot==''）専用。
 *
 * PUT セマンティクス（全置換）:
 *   - folders: payload の folder_id を upsert し、無いものを DELETE。
 *              upsert の更新対象に rule_*（スマートフォルダのルール列）は含めない
 *              （PUT 全置換でルール設定が消えないように。ルールは folder-settings API が管理）。
 *              DELETE したフォルダは、付随するスマートフォルダのデータ
 *              （alpha_folder_threshold_ja / alpha_folder_seen_ja / フォルダ内の auto アイテム）も掃除する
 *              （rule_* はフォルダ行と一緒に消える）。
 *   - items:   payload の open_chat_id を upsert（既存行の source は維持）し、
 *              無いものを DELETE。ただし source='auto' かつ added_at > loadedAt の
 *              行は loadedAt=null 時・または added_at が loadedAt より新しい場合は
 *              削除しない（スマートフォルダの自動追加行を上書き消去しない）。
 */
class AlphaMylistRepository
{
    // ====================== 取得 ======================

    /**
     * ユーザーのマイリスト全体を取得する。
     *
     * @return array{
     *   exists: bool,
     *   folders: list<array{id:string, name:string, parentId:string|null, order:int, expanded:bool}>,
     *   items: list<array{id:int, folderId:string|null, order:int, addedAt:string, source:string}>,
     *   serverTime: string
     * }
     */
    public function getMylist(string $userId): array
    {
        UserLogDB::connect();

        $folderRows = UserLogDB::fetchAll(
            "SELECT folder_id, name, parent_id, sort_order, expanded
             FROM alpha_mylist_folder_ja
             WHERE user_id = :uid
             ORDER BY sort_order ASC, folder_id ASC",
            ['uid' => $userId]
        );

        $itemRows = UserLogDB::fetchAll(
            "SELECT open_chat_id, folder_id, sort_order, added_at, source
             FROM alpha_mylist_item_ja
             WHERE user_id = :uid
             ORDER BY sort_order ASC, open_chat_id ASC",
            ['uid' => $userId]
        );

        $exists = count($folderRows) > 0 || count($itemRows) > 0;

        $folders = array_map(static fn($r) => [
            'id'       => (string)$r['folder_id'],
            'name'     => (string)$r['name'],
            'parentId' => $r['parent_id'] !== null ? (string)$r['parent_id'] : null,
            'order'    => (int)$r['sort_order'],
            'expanded' => (bool)$r['expanded'],
        ], $folderRows);

        $items = array_map(static fn($r) => [
            'id'       => (int)$r['open_chat_id'],
            'folderId' => $r['folder_id'] !== null ? (string)$r['folder_id'] : null,
            'order'    => (int)$r['sort_order'],
            'addedAt'  => (string)$r['added_at'],
            'source'   => (string)$r['source'],
        ], $itemRows);

        return [
            'exists'     => $exists,
            'folders'    => $folders,
            'items'      => $items,
            'serverTime' => date('Y-m-d H:i:s'),
        ];
    }

    // ====================== 全置換 (PUT) ======================

    /**
     * マイリスト全体を置換する（PUT セマンティクス）。
     *
     * @param list<array{id:string, name:string, parentId:string|null, order:int, expanded:bool}> $folders
     * @param list<array{id:int, folderId:string|null, order:int, addedAt?:string, source?:string}> $items
     * @param string|null $loadedAt フロントがデータを読み込んだ時刻（Y-m-d H:i:s）。
     *                              null 時は auto 行を一切削除しない。
     */
    public function replaceMylist(string $userId, array $folders, array $items, ?string $loadedAt): void
    {
        UserLogDB::connect();
        $pdo = UserLogDB::$pdo;
        assert($pdo !== null);

        $pdo->beginTransaction();
        try {
            $this->replaceFolders($userId, $folders);
            $this->replaceItems($userId, $items, $loadedAt);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param list<array{id:string, name:string, parentId:string|null, order:int, expanded:bool}> $folders */
    private function replaceFolders(string $userId, array $folders): void
    {
        // 削除対象（payload に無い既存フォルダ）の特定用に、現状の folder_id を先に取得
        $existingRows = UserLogDB::fetchAll(
            "SELECT folder_id FROM alpha_mylist_folder_ja WHERE user_id = :uid",
            ['uid' => $userId]
        );
        $existingIds = array_map(static fn($r) => (string)$r['folder_id'], $existingRows);

        // upsert
        $keepIds = [];
        foreach ($folders as $f) {
            $folderId = trim((string)($f['id'] ?? ''));
            if ($folderId === '') {
                continue;
            }
            $name     = mb_substr(trim((string)($f['name'] ?? '')), 0, 190);
            $parentId = ($f['parentId'] ?? null) !== null ? trim((string)$f['parentId']) : null;
            $order    = (int)($f['order'] ?? 0);
            $expanded = isset($f['expanded']) ? ($f['expanded'] ? 1 : 0) : 1;

            UserLogDB::execute(
                "INSERT INTO alpha_mylist_folder_ja
                    (user_id, folder_id, name, parent_id, sort_order, expanded)
                 VALUES (:uid, :fid, :name, :pid, :ord, :exp)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    parent_id = VALUES(parent_id),
                    sort_order = VALUES(sort_order),
                    expanded = VALUES(expanded),
                    updated_at = current_timestamp()",
                [
                    'uid'  => $userId,
                    'fid'  => $folderId,
                    'name' => $name,
                    'pid'  => $parentId,
                    'ord'  => $order,
                    'exp'  => $expanded,
                ]
            );
            $keepIds[] = $folderId;
        }

        // payload にない folder を削除
        if (empty($keepIds)) {
            UserLogDB::execute(
                "DELETE FROM alpha_mylist_folder_ja WHERE user_id = :uid",
                ['uid' => $userId]
            );
        } else {
            $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
            UserLogDB::execute(
                "DELETE FROM alpha_mylist_folder_ja
                 WHERE user_id = ?
                   AND folder_id NOT IN ({$placeholders})",
                array_merge([$userId], $keepIds)
            );
        }

        // 削除したフォルダのスマートフォルダ関連データを掃除
        // （rule_* はフォルダ行と一緒に消えるので、しきい値・seen・auto アイテムを明示削除）
        $deletedIds = array_values(array_diff($existingIds, $keepIds));
        $this->cleanupDeletedFolders($userId, $deletedIds);
    }

    /**
     * フォルダ削除に伴うスマートフォルダ関連データの掃除。
     *   - alpha_folder_threshold_ja … フォルダ単位アラートのしきい値
     *   - alpha_folder_seen_ja      … 再追加防止の seen 記録
     *   - alpha_mylist_item_ja      … フォルダ内の source='auto' アイテム
     *     （manual はフロントが items payload で管理するため触らない。
     *      auto はフォルダ削除と同時に意味を失う＝放置すると消えたフォルダIDを指したまま
     *      auto 保護ガードで削除不能になるため、ここで消す）
     *
     * @param string[] $deletedFolderIds
     */
    private function cleanupDeletedFolders(string $userId, array $deletedFolderIds): void
    {
        if (empty($deletedFolderIds)) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($deletedFolderIds), '?'));
        $params = array_merge([$userId], $deletedFolderIds);

        UserLogDB::execute(
            "DELETE FROM alpha_folder_threshold_ja
             WHERE user_id = ? AND folder_id IN ({$placeholders})",
            $params
        );
        UserLogDB::execute(
            "DELETE FROM alpha_folder_seen_ja
             WHERE user_id = ? AND folder_id IN ({$placeholders})",
            $params
        );
        UserLogDB::execute(
            "DELETE FROM alpha_mylist_item_ja
             WHERE user_id = ? AND source = 'auto' AND folder_id IN ({$placeholders})",
            $params
        );
    }

    /**
     * @param list<array{id:int, folderId:string|null, order:int, addedAt?:string, source?:string}> $items
     * @param string|null $loadedAt
     */
    private function replaceItems(string $userId, array $items, ?string $loadedAt): void
    {
        // upsert
        $keepIds = [];
        foreach ($items as $item) {
            $ocId = (int)($item['id'] ?? 0);
            if ($ocId < 1) {
                continue;
            }
            $folderId = ($item['folderId'] ?? null) !== null ? trim((string)$item['folderId']) : null;
            $order    = (int)($item['order'] ?? 0);
            $source   = in_array($item['source'] ?? '', ['manual', 'auto'], true)
                ? (string)$item['source'] : 'manual';
            $addedAt  = self::normalizeDatetime($item['addedAt'] ?? null);

            // 既存行が存在する場合は source を維持する（INSERT IGNORE 後に UPDATE で他列を更新）。
            // 新規行のみ source を payload の値（既定 manual）で挿入する。
            UserLogDB::execute(
                "INSERT INTO alpha_mylist_item_ja
                    (user_id, open_chat_id, folder_id, sort_order, source, added_at)
                 VALUES (:uid, :oc, :fid, :ord, :src, :addat)
                 ON DUPLICATE KEY UPDATE
                    folder_id  = VALUES(folder_id),
                    sort_order = VALUES(sort_order),
                    added_at   = VALUES(added_at)",
                [
                    'uid'   => $userId,
                    'oc'    => $ocId,
                    'fid'   => $folderId,
                    'ord'   => $order,
                    'src'   => $source,
                    'addat' => $addedAt,
                ]
            );
            $keepIds[] = $ocId;
        }

        // payload にないアイテムを削除（auto 行の保護ガード付き）
        if (empty($keepIds)) {
            // 全削除対象だが auto 保護が必要
            if ($loadedAt === null) {
                // auto 行は一切削除しない
                UserLogDB::execute(
                    "DELETE FROM alpha_mylist_item_ja
                     WHERE user_id = :uid AND source != 'auto'",
                    ['uid' => $userId]
                );
            } else {
                // added_at <= loadedAt の auto 行は削除してよい。それより新しい auto 行は残す。
                UserLogDB::execute(
                    "DELETE FROM alpha_mylist_item_ja
                     WHERE user_id = :uid
                       AND NOT (source = 'auto' AND added_at > :lat)",
                    ['uid' => $userId, 'lat' => $loadedAt]
                );
            }
        } else {
            $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
            if ($loadedAt === null) {
                // auto 行を一切削除しない
                UserLogDB::execute(
                    "DELETE FROM alpha_mylist_item_ja
                     WHERE user_id = ?
                       AND open_chat_id NOT IN ({$placeholders})
                       AND source != 'auto'",
                    array_merge([$userId], $keepIds)
                );
            } else {
                // added_at > loadedAt の auto 行は保護する
                UserLogDB::execute(
                    "DELETE FROM alpha_mylist_item_ja
                     WHERE user_id = ?
                       AND open_chat_id NOT IN ({$placeholders})
                       AND NOT (source = 'auto' AND added_at > ?)",
                    array_merge([$userId], $keepIds, [$loadedAt])
                );
            }
        }
    }

    // ====================== 単発操作 ======================

    /**
     * アイテムを1件追加（重複時は folderId を更新して upsert）。
     * sort_order は同フォルダ内 max+1（0始まり）。source は常に 'manual'。
     */
    public function addItem(string $userId, int $openChatId, ?string $folderId): void
    {
        UserLogDB::connect();

        $maxRow = UserLogDB::fetch(
            "SELECT MAX(sort_order) AS max_ord
             FROM alpha_mylist_item_ja
             WHERE user_id = :uid AND " . ($folderId !== null ? "folder_id = :fid" : "folder_id IS NULL"),
            $folderId !== null ? ['uid' => $userId, 'fid' => $folderId] : ['uid' => $userId]
        );

        $nextOrder = ($maxRow && $maxRow['max_ord'] !== null) ? (int)$maxRow['max_ord'] + 1 : 0;

        UserLogDB::execute(
            "INSERT INTO alpha_mylist_item_ja
                (user_id, open_chat_id, folder_id, sort_order, source, added_at)
             VALUES (:uid, :oc, :fid, :ord, 'manual', current_timestamp())
             ON DUPLICATE KEY UPDATE
                folder_id  = VALUES(folder_id),
                sort_order = VALUES(sort_order)",
            [
                'uid' => $userId,
                'oc'  => $openChatId,
                'fid' => $folderId,
                'ord' => $nextOrder,
            ]
        );
    }

    /**
     * アイテムを1件削除。
     */
    public function removeItem(string $userId, int $openChatId): void
    {
        UserLogDB::connect();
        UserLogDB::execute(
            "DELETE FROM alpha_mylist_item_ja WHERE user_id = :uid AND open_chat_id = :oc",
            ['uid' => $userId, 'oc' => $openChatId]
        );
    }

    /**
     * クライアントから来る日時文字列を MySQL DATETIME 形式へ正規化する。
     * localStorage 由来の addedAt は ISO 8601（例 2026-06-08T00:00:00.000Z）で届くため
     * そのまま INSERT すると SQLSTATE[22007] になる。不正値・欠落は現在時刻。
     */
    private static function normalizeDatetime(mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            try {
                return (new \DateTime($value))
                    ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                    ->format('Y-m-d H:i:s');
            } catch (\Exception) {
                // 不正な日時文字列は現在時刻にフォールバック
            }
        }
        return date('Y-m-d H:i:s');
    }
}

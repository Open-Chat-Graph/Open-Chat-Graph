<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories\Alpha;

use App\Models\UserLogRepositories\UserLogDB;

/**
 * ウォッチ部屋の機微検知 (room_change) 用スナップショットのリポジトリ。
 *
 * open_chat は上書き更新で変更履歴が残らないため、ウォッチされている部屋の
 * name/description/category を alpha_room_snapshot_ja（1部屋1行・ユーザー横断）に退避し、
 * 毎時の現在値と比較して「部屋情報の変更」を検知する。
 *
 * 接続規約: αテーブル（alpha_xxx_ja）は userlog DB（UserLogDB）。
 */
class AlphaRoomSnapshotRepository
{
    /**
     * open_chat_id 配列のスナップショットを取得する。
     *
     * @param int[] $ocIds
     * @return array<int, array{open_chat_id:int, name:string, description:string, category:?int}> open_chat_id => row
     */
    public function getSnapshotMap(array $ocIds): array
    {
        $ocIds = array_values(array_filter(array_map('intval', $ocIds), static fn($i) => $i > 0));
        if (empty($ocIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ocIds), '?'));
        $rows = UserLogDB::fetchAll(
            "SELECT open_chat_id, name, description, category
             FROM alpha_room_snapshot_ja WHERE open_chat_id IN ({$placeholders})",
            $ocIds
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['open_chat_id']] = [
                'open_chat_id' => (int)$r['open_chat_id'],
                'name' => (string)$r['name'],
                'description' => (string)$r['description'],
                'category' => $r['category'] === null ? null : (int)$r['category'],
            ];
        }
        return $map;
    }

    /**
     * スナップショットを upsert する（seed・変更検知後の更新の両方で使う）。
     */
    public function upsertSnapshot(int $ocId, string $name, string $description, ?int $category): void
    {
        UserLogDB::execute(
            "INSERT INTO alpha_room_snapshot_ja (open_chat_id, name, description, category)
             VALUES (:oc, :name, :descr, :cat)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                category = VALUES(category),
                updated_at = current_timestamp()",
            ['oc' => $ocId, 'name' => $name, 'descr' => $description, 'cat' => $category]
        );
    }

    /**
     * ウォッチされていない部屋のスナップショットを掃除する。
     * $keepOcIds が空（ウォッチが1件も無い）なら全行削除する。
     *
     * @param int[] $keepOcIds 現在ウォッチされている open_chat_id の集合
     */
    public function deleteSnapshotsNotIn(array $keepOcIds): void
    {
        $keepOcIds = array_values(array_filter(array_map('intval', $keepOcIds), static fn($i) => $i > 0));
        if (empty($keepOcIds)) {
            UserLogDB::execute("DELETE FROM alpha_room_snapshot_ja");
            return;
        }
        $placeholders = implode(',', array_fill(0, count($keepOcIds), '?'));
        UserLogDB::execute(
            "DELETE FROM alpha_room_snapshot_ja WHERE open_chat_id NOT IN ({$placeholders})",
            $keepOcIds
        );
    }
}

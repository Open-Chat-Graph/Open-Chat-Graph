<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories\Alpha;

use App\Models\Repositories\DB;
use App\Models\UserLogRepositories\UserLogDB;

/**
 * スマートフォルダ（フォルダのルール・フォルダ単位しきい値・seen記録・auto追加）用リポジトリ。
 *
 * - ルール: alpha_mylist_folder_ja の rule_* 列（keyword 必須＋category 任意）
 * - しきい値: alpha_folder_threshold_ja（フォルダにつき1組。意味は alpha_mylist_threshold_ja と同じ）
 * - 再追加防止: alpha_folder_seen_ja（(user, folder, oc) を恒久記録。seen は二度と自動追加しない）
 * - 自動追加: alpha_mylist_item_ja へ source='auto' で INSERT IGNORE
 *   （PK は (user_id, open_chat_id) なので、既にマイリストのどこかにある部屋は動かさない）
 *
 * 接続規約: αテーブルは userlog DB（UserLogDB）。open_chat の一致部屋検索は
 * ocreview 単独参照なので従来どおり DB を使う（跨ぎJOINはしない）。ja（urlRoot==''）専用。
 */
class AlphaSmartFolderRepository
{
    // ====================== フォルダ／ルール ======================

    /**
     * ユーザー所有のフォルダ1件（ルール列込み）を返す。無ければ null。
     *
     * @return ?array{folder_id:string, name:string, rule_keyword:?string, rule_category:?int, rule_enabled:bool, rule_created_at:?string}
     */
    public function getFolderRow(string $userId, string $folderId): ?array
    {
        $row = UserLogDB::fetch(
            "SELECT folder_id, name, rule_keyword, rule_category, rule_enabled, rule_created_at
             FROM alpha_mylist_folder_ja
             WHERE user_id = :uid AND folder_id = :fid",
            ['uid' => $userId, 'fid' => $folderId]
        );
        if (!$row) {
            return null;
        }
        return [
            'folder_id' => (string)$row['folder_id'],
            'name' => (string)$row['name'],
            'rule_keyword' => $row['rule_keyword'] !== null ? (string)$row['rule_keyword'] : null,
            'rule_category' => $row['rule_category'] !== null ? (int)$row['rule_category'] : null,
            'rule_enabled' => (bool)$row['rule_enabled'],
            'rule_created_at' => $row['rule_created_at'] !== null ? (string)$row['rule_created_at'] : null,
        ];
    }

    /**
     * ルールを保存する。rule_created_at は呼び出し側（Service/Controller）が
     * 「新規有効化・keyword/category 変更時は now、それ以外は既存値維持」を解決して渡す。
     */
    public function saveRule(
        string $userId,
        string $folderId,
        string $keyword,
        ?int $category,
        bool $enabled,
        ?string $ruleCreatedAt,
    ): void {
        UserLogDB::execute(
            "UPDATE alpha_mylist_folder_ja
             SET rule_keyword = :kw, rule_category = :cat, rule_enabled = :en, rule_created_at = :rca
             WHERE user_id = :uid AND folder_id = :fid",
            [
                'kw' => $keyword,
                'cat' => $category,
                'en' => $enabled ? 1 : 0,
                'rca' => $ruleCreatedAt,
                'uid' => $userId,
                'fid' => $folderId,
            ]
        );
    }

    /** ルールを解除する（rule_* を NULL/0 へ。auto アイテムと seen は残す）。 */
    public function clearRule(string $userId, string $folderId): void
    {
        UserLogDB::execute(
            "UPDATE alpha_mylist_folder_ja
             SET rule_keyword = NULL, rule_category = NULL, rule_enabled = 0, rule_created_at = NULL
             WHERE user_id = :uid AND folder_id = :fid",
            ['uid' => $userId, 'fid' => $folderId]
        );
    }

    /**
     * 有効なルールを全ユーザー横断で返す（毎時 cron 用）。
     *
     * @return array<int, array{user_id:string, folder_id:string, folder_name:string, keyword:string, category:?int, rule_created_at:string}>
     */
    public function getAllEnabledRules(): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT user_id, folder_id, name, rule_keyword, rule_category, rule_created_at
             FROM alpha_mylist_folder_ja
             WHERE rule_enabled = 1
               AND rule_keyword IS NOT NULL AND rule_keyword != ''
               AND rule_created_at IS NOT NULL"
        );
        return array_map(static fn($r) => [
            'user_id' => (string)$r['user_id'],
            'folder_id' => (string)$r['folder_id'],
            'folder_name' => (string)$r['name'],
            'keyword' => (string)$r['rule_keyword'],
            'category' => $r['rule_category'] !== null ? (int)$r['rule_category'] : null,
            'rule_created_at' => (string)$r['rule_created_at'],
        ], $rows);
    }

    // ====================== フォルダしきい値 ======================

    /**
     * @return ?array{up_percent:?float, down_percent:?float, up_member:?int, down_member:?int, enabled:bool}
     */
    public function getThreshold(string $userId, string $folderId): ?array
    {
        $row = UserLogDB::fetch(
            "SELECT up_percent, down_percent, up_member, down_member, enabled
             FROM alpha_folder_threshold_ja
             WHERE user_id = :uid AND folder_id = :fid",
            ['uid' => $userId, 'fid' => $folderId]
        );
        if (!$row) {
            return null;
        }
        return [
            'up_percent' => $row['up_percent'] !== null ? (float)$row['up_percent'] : null,
            'down_percent' => $row['down_percent'] !== null ? (float)$row['down_percent'] : null,
            'up_member' => $row['up_member'] !== null ? (int)$row['up_member'] : null,
            'down_member' => $row['down_member'] !== null ? (int)$row['down_member'] : null,
            'enabled' => (bool)$row['enabled'],
        ];
    }

    public function saveThreshold(
        string $userId,
        string $folderId,
        ?float $upPercent,
        ?float $downPercent,
        ?int $upMember,
        ?int $downMember,
        bool $enabled,
    ): void {
        UserLogDB::execute(
            "INSERT INTO alpha_folder_threshold_ja
                (user_id, folder_id, up_percent, down_percent, up_member, down_member, enabled)
             VALUES (:uid, :fid, :up, :dp, :um, :dm, :en)
             ON DUPLICATE KEY UPDATE up_percent = VALUES(up_percent),
                                     down_percent = VALUES(down_percent),
                                     up_member = VALUES(up_member),
                                     down_member = VALUES(down_member),
                                     enabled = VALUES(enabled),
                                     updated_at = current_timestamp()",
            [
                'uid' => $userId,
                'fid' => $folderId,
                'up' => $upPercent,
                'dp' => $downPercent,
                'um' => $upMember,
                'dm' => $downMember,
                'en' => $enabled ? 1 : 0,
            ]
        );
    }

    public function deleteThreshold(string $userId, string $folderId): void
    {
        UserLogDB::execute(
            "DELETE FROM alpha_folder_threshold_ja WHERE user_id = :uid AND folder_id = :fid",
            ['uid' => $userId, 'fid' => $folderId]
        );
    }

    /**
     * 有効なフォルダしきい値を全ユーザー横断で返す（毎時 cron 用）。
     * フォルダが消えている行は対象外（JOIN で除外）。フォルダ名も併せて返す。
     *
     * @return array<int, array{user_id:string, folder_id:string, folder_name:string, up_percent:?float, down_percent:?float, up_member:?int, down_member:?int}>
     */
    public function getAllEnabledThresholds(): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT t.user_id, t.folder_id, f.name, t.up_percent, t.down_percent, t.up_member, t.down_member
             FROM alpha_folder_threshold_ja AS t
             JOIN alpha_mylist_folder_ja AS f
               ON f.user_id = t.user_id AND f.folder_id = t.folder_id
             WHERE t.enabled = 1"
        );
        return array_map(static fn($r) => [
            'user_id' => (string)$r['user_id'],
            'folder_id' => (string)$r['folder_id'],
            'folder_name' => (string)$r['name'],
            'up_percent' => $r['up_percent'] !== null ? (float)$r['up_percent'] : null,
            'down_percent' => $r['down_percent'] !== null ? (float)$r['down_percent'] : null,
            'up_member' => $r['up_member'] !== null ? (int)$r['up_member'] : null,
            'down_member' => $r['down_member'] !== null ? (int)$r['down_member'] : null,
        ], $rows);
    }

    // ====================== フォルダ木／アイテム ======================

    /**
     * ユーザーの全フォルダの親子関係を返す（子孫フォルダの再帰解決用）。
     *
     * @return array<string, ?string> folder_id => parent_id
     */
    public function getUserFolderParents(string $userId): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT folder_id, parent_id FROM alpha_mylist_folder_ja WHERE user_id = :uid",
            ['uid' => $userId]
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(string)$r['folder_id']] = $r['parent_id'] !== null ? (string)$r['parent_id'] : null;
        }
        return $map;
    }

    /**
     * 指定フォルダ群（直下）にあるアイテムの open_chat_id を返す。
     *
     * @param string[] $folderIds
     * @return int[]
     */
    public function getItemOcIdsInFolders(string $userId, array $folderIds): array
    {
        $folderIds = array_values(array_filter($folderIds, static fn($f) => $f !== ''));
        if (empty($folderIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($folderIds), '?'));
        $rows = UserLogDB::fetchAll(
            "SELECT open_chat_id FROM alpha_mylist_item_ja
             WHERE user_id = ? AND folder_id IN ({$placeholders})",
            array_merge([$userId], $folderIds)
        );
        return array_map(static fn($r) => (int)$r['open_chat_id'], $rows);
    }

    /** 同フォルダ内の次の sort_order（max+1・0始まり）。 */
    public function getNextSortOrder(string $userId, string $folderId): int
    {
        $row = UserLogDB::fetch(
            "SELECT MAX(sort_order) AS max_ord FROM alpha_mylist_item_ja
             WHERE user_id = :uid AND folder_id = :fid",
            ['uid' => $userId, 'fid' => $folderId]
        );
        return ($row && $row['max_ord'] !== null) ? (int)$row['max_ord'] + 1 : 0;
    }

    /**
     * auto アイテムを1件追加する。既にユーザーのマイリストに存在する部屋
     * （PK (user_id, open_chat_id) 衝突）は何もしない（別フォルダから動かさない）。
     *
     * @return bool 新規に追加されたら true
     */
    public function insertAutoItem(string $userId, string $folderId, int $openChatId, int $sortOrder): bool
    {
        $stmt = UserLogDB::execute(
            "INSERT IGNORE INTO alpha_mylist_item_ja
                (user_id, open_chat_id, folder_id, sort_order, source, added_at)
             VALUES (:uid, :oc, :fid, :ord, 'auto', current_timestamp())",
            ['uid' => $userId, 'oc' => $openChatId, 'fid' => $folderId, 'ord' => $sortOrder]
        );
        return $stmt->rowCount() > 0;
    }

    // ====================== seen（再追加防止） ======================

    /**
     * フォルダの seen 済み open_chat_id 集合を返す（open_chat_id => true）。
     *
     * @return array<int, true>
     */
    public function getSeenOcIds(string $userId, string $folderId): array
    {
        $rows = UserLogDB::fetchAll(
            "SELECT open_chat_id FROM alpha_folder_seen_ja
             WHERE user_id = :uid AND folder_id = :fid",
            ['uid' => $userId, 'fid' => $folderId]
        );
        $set = [];
        foreach ($rows as $r) {
            $set[(int)$r['open_chat_id']] = true;
        }
        return $set;
    }

    public function markSeen(string $userId, string $folderId, int $openChatId): void
    {
        UserLogDB::execute(
            "INSERT IGNORE INTO alpha_folder_seen_ja (user_id, folder_id, open_chat_id)
             VALUES (:uid, :fid, :oc)",
            ['uid' => $userId, 'fid' => $folderId, 'oc' => $openChatId]
        );
    }

    // ====================== open_chat 一致部屋検索（ocreview 側） ======================

    /**
     * ルールに一致する部屋を人数上位から返す（初回フィル用）。
     * 一致条件は検索API（AlphaQueryBuilder）と同じ:
     *   キーワードを空白（全角含む）で分割し、各語が name か description に LIKE 部分一致（AND）。
     *   category は指定時のみ WHERE（0/null は全カテゴリ）。
     *
     * @return array<int, array{id:int, name:string, member:int, created_at:?string}>
     */
    public function findMatchingRooms(string $keyword, ?int $category, int $limit): array
    {
        [$where, $params] = $this->buildMatchWhere($keyword, $category);
        if ($where === null) {
            return [];
        }

        DB::connect();
        $rows = DB::fetchAll(
            "SELECT id, name, member, created_at
             FROM open_chat
             WHERE {$where}
             ORDER BY member DESC
             LIMIT " . max(1, $limit),
            $params
        );
        return $this->mapRoomRows($rows);
    }

    /**
     * ルールに一致し「:since 以降にDB収録された」部屋を収録日昇順で返す（毎時の自動追加用）。
     * 「DB収録日」= open_chat.created_at（こちらのDBへの登録日時。
     * キーワードウォッチの新着判定と同じカラム。api_created_at はLINE側の開設日なので使わない）。
     *
     * @return array<int, array{id:int, name:string, member:int, created_at:?string}>
     */
    public function findNewMatchingRooms(string $keyword, ?int $category, string $since, int $limit): array
    {
        [$where, $params] = $this->buildMatchWhere($keyword, $category);
        if ($where === null) {
            return [];
        }
        $params['since'] = $since;

        DB::connect();
        $rows = DB::fetchAll(
            "SELECT id, name, member, created_at
             FROM open_chat
             WHERE {$where} AND created_at >= :since
             ORDER BY created_at ASC
             LIMIT " . max(1, $limit),
            $params
        );
        return $this->mapRoomRows($rows);
    }

    /**
     * キーワード（空白区切りAND）＋カテゴリの WHERE 句を組み立てる。
     * 語が1つも無い場合は null（マッチ検索しない）。
     *
     * @return array{0:?string, 1:array<string, mixed>}
     */
    private function buildMatchWhere(string $keyword, ?int $category): array
    {
        $normalized = str_replace('　', ' ', $keyword);
        $words = array_values(array_filter(
            array_map('trim', explode(' ', $normalized)),
            static fn($w) => $w !== ''
        ));
        if (empty($words)) {
            return [null, []];
        }

        $conditions = [];
        $params = [];
        foreach ($words as $i => $w) {
            $conditions[] = "(name LIKE :kw{$i} OR description LIKE :kw{$i})";
            $params["kw{$i}"] = "%{$w}%";
        }
        if ($category !== null && $category > 0) {
            $conditions[] = "category = :category";
            $params['category'] = $category;
        }
        return [implode(' AND ', $conditions), $params];
    }

    /** @return array<int, array{id:int, name:string, member:int, created_at:?string}> */
    private function mapRoomRows(array $rows): array
    {
        return array_map(static fn($r) => [
            'id' => (int)$r['id'],
            'name' => (string)$r['name'],
            'member' => (int)$r['member'],
            'created_at' => $r['created_at'] !== null ? (string)$r['created_at'] : null,
        ], $rows);
    }
}

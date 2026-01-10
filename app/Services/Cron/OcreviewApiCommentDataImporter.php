<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\Services\Admin\AdminTool;
use PDO;
use PDOStatement;

/**
 * ocgraph_comment データベース（MySQL）から ocgraph_sqlapi データベース（SQLite）へのコメントデータインポートサービス
 *
 * 【対象テーブル】
 * - comment: コメントテーブル（IDベース + flag差分同期）
 * - like → comment_like: いいねテーブル（ID配列比較による完全同期）
 * - ban_room: オープンチャット投稿禁止テーブル（IDベース）
 * - ban_user: ユーザー投稿禁止テーブル（IDベース）
 * - log → comment_log: ログテーブル（IDベース）
 */
class OcreviewApiCommentDataImporter
{
    protected PDO $sourceCommentPdo;
    protected PDO $targetPdo;

    /** Discord通知カウンター */
    private int $discordNotificationCount = 0;

    /** Discord通知間隔（何件処理するごとに通知するか） */
    private const DISCORD_NOTIFY_INTERVAL = 100;

    /** チャンクサイズ（MySQL一括処理） */
    private const CHUNK_SIZE = 200;

    public function __construct(
        PDO $sourceCommentPdo,
        PDO $targetPdo
    ) {
        $this->sourceCommentPdo = $sourceCommentPdo;
        $this->targetPdo = $targetPdo;
    }

    /**
     * コメント関連データのインポート
     *
     * ocgraph_commentデータベースから以下のテーブルを同期:
     * - comment: コメントテーブル（IDベース + flag差分同期）
     * - like: いいねテーブル（ID配列比較による完全同期）
     * - ban_room: オープンチャット投稿禁止テーブル（IDベース）
     * - ban_user: ユーザー投稿禁止テーブル（IDベース）
     * - log: ログテーブル（IDベース）
     */
    public function execute(): void
    {
        // テーブルが存在しない場合は作成
        $this->ensureCommentTablesExist();

        // コメントテーブルのインポート（IDベース + flag差分同期）
        $this->importComments();

        // いいねテーブルのインポート（ID配列比較による完全同期）
        $this->importCommentLikes();

        // ban_roomテーブルのインポート（IDベース）
        $this->importBanRooms();

        // ban_userテーブルのインポート（IDベース）
        $this->importBanUsers();

        // logテーブルのインポート（IDベース）
        $this->importCommentLogs();
    }

    /**
     * コメント関連テーブルが存在しない場合は作成
     *
     * 本番環境では既にSQLiteファイルが構築されているが、
     * 新しいテーブルは存在しないため自動作成する。
     * スキーマファイル全体を実行（CREATE TABLE IF NOT EXISTSなので既存テーブルには影響しない）。
     */
    private function ensureCommentTablesExist(): void
    {
        $schemaPath = \App\Config\AppConfig::ROOT_PATH . 'storage/ja/SQLite/template/sqlapi_schema.sql';

        if (!file_exists($schemaPath)) {
            throw new \RuntimeException("Schema file not found: {$schemaPath}");
        }

        $schema = file_get_contents($schemaPath);

        // スキーマファイル全体を実行
        // CREATE TABLE IF NOT EXISTSなので、既存テーブルには影響せず、
        // 存在しないコメント関連テーブルのみが作成される
        $this->targetPdo->exec($schema);
    }

    /**
     * コメントテーブルのインポート
     *
     * 【差分同期の仕組み】
     * 1. ターゲットDBのcomment_idの最大値以降のレコードをIDベースで追加
     * 2. 既存レコードのflagカラムの変更を検出して更新
     */
    private function importComments(): void
    {
        // ターゲットDBから最大comment_idを取得
        $stmt = $this->targetPdo->query("SELECT COALESCE(MAX(comment_id), 0) as max_id FROM comment");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxId = (int)$result['max_id'];

        // ソースDBから差分レコード数を取得
        $stmt = $this->sourceCommentPdo->prepare("SELECT COUNT(*) FROM comment WHERE comment_id > ?");
        $stmt->execute([$maxId]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            // 差分レコードのみを取得してインポート
            $query = "
                SELECT
                    comment_id,
                    open_chat_id,
                    id,
                    user_id,
                    name,
                    text,
                    time,
                    flag
                FROM
                    comment
                WHERE comment_id > ?
                ORDER BY comment_id
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->sourceCommentPdo->prepare($query);

            $this->processInChunks(
                $stmt,
                [1 => [$maxId, PDO::PARAM_INT]],
                $count,
                self::CHUNK_SIZE,
                function (array $data) {
                    if (!empty($data)) {
                        $this->sqliteInsert('comment', $data);
                    }
                },
                'comment: %d / %d 件処理完了'
            );
        }

        // flagカラムの差分同期
        $this->syncCommentFlags();
    }

    /**
     * コメントのflagカラムの差分を同期
     *
     * ターゲットDB内の全comment_idとflagの組み合わせをソースDBと比較し、
     * 差分があるレコードのみを更新。
     */
    private function syncCommentFlags(): void
    {
        // ターゲットDB内の全レコード（comment_id, flag）を取得
        $targetData = $this->targetPdo->query("SELECT comment_id, flag FROM comment")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($targetData)) {
            return;
        }

        // 連想配列に変換（高速比較用）
        $targetLookup = [];
        foreach ($targetData as $record) {
            $targetLookup[$record['comment_id']] = $record['flag'];
        }

        $commentIds = array_keys($targetLookup);

        // チャンク単位でソースDBから取得して比較
        $chunks = array_chunk($commentIds, self::CHUNK_SIZE);

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $query = "SELECT comment_id, flag FROM comment WHERE comment_id IN ($placeholders)";
            $stmt = $this->sourceCommentPdo->prepare($query);
            $stmt->execute($chunk);
            $sourceData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $updatesNeeded = [];
            foreach ($sourceData as $row) {
                $commentId = $row['comment_id'];
                if (isset($targetLookup[$commentId]) && $targetLookup[$commentId] !== $row['flag']) {
                    $updatesNeeded[] = $row;
                }
            }

            if (!empty($updatesNeeded)) {
                $this->bulkUpdateCommentFlags($updatesNeeded);
            }
        }
    }

    /**
     * コメントのflagカラムを一括更新（CASE文で高速化）
     */
    private function bulkUpdateCommentFlags(array $records): void
    {
        if (empty($records)) {
            return;
        }

        // CASE文を使った一括UPDATE
        $whenClauses = [];
        $commentIds = [];
        $params = [];

        foreach ($records as $record) {
            $whenClauses[] = "WHEN ? THEN ?";
            $commentIds[] = $record['comment_id'];
            $params[] = $record['comment_id'];
            $params[] = $record['flag'];
        }

        // 全comment_idを最後に追加
        $params = array_merge($params, $commentIds);

        $whenClause = implode(' ', $whenClauses);
        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));

        $sql = "UPDATE comment SET flag = CASE comment_id {$whenClause} END WHERE comment_id IN ($placeholders)";

        $stmt = $this->targetPdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * いいねテーブルのインポート
     *
     * 【差分同期の仕組み】
     * いいねは取り消すとレコードが削除されるため、IDベースの追加だけでは整合性が取れない。
     * そのため、ソースとターゲットのID配列を比較して差分を適用する。
     * - ソースに存在しターゲットに存在しないID: 追加
     * - ターゲットに存在しソースに存在しないID: 削除
     */
    private function importCommentLikes(): void
    {
        // ソースとターゲットの全IDを取得
        $sourceIds = $this->sourceCommentPdo->query("SELECT id FROM `like` ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $targetIds = $this->targetPdo->query("SELECT id FROM comment_like ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

        // 差分を計算
        $idsToInsert = array_diff($sourceIds, $targetIds);
        $idsToDelete = array_diff($targetIds, $sourceIds);

        // 追加すべきレコードをインポート
        if (!empty($idsToInsert)) {
            $chunks = array_chunk($idsToInsert, self::CHUNK_SIZE);

            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $query = "SELECT id, comment_id, user_id, type, time FROM `like` WHERE id IN ($placeholders)";
                $stmt = $this->sourceCommentPdo->prepare($query);
                $stmt->execute($chunk);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($data)) {
                    $this->sqliteInsert('comment_like', $data);
                }
            }

            $this->log(sprintf('comment_like: %d 件追加', count($idsToInsert)));
        }

        // 削除すべきレコードを削除
        if (!empty($idsToDelete)) {
            $chunks = array_chunk($idsToDelete, self::CHUNK_SIZE);

            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $query = "DELETE FROM comment_like WHERE id IN ($placeholders)";
                $stmt = $this->targetPdo->prepare($query);
                $stmt->execute($chunk);
            }

            $this->log(sprintf('comment_like: %d 件削除', count($idsToDelete)));
        }
    }

    /**
     * ban_roomテーブルのインポート（IDベース）
     */
    private function importBanRooms(): void
    {
        // ターゲットDBから最大IDを取得
        $stmt = $this->targetPdo->query("SELECT COALESCE(MAX(id), 0) as max_id FROM ban_room");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxId = (int)$result['max_id'];

        // ソースDBから差分レコード数を取得
        $stmt = $this->sourceCommentPdo->prepare("SELECT COUNT(*) FROM ban_room WHERE id > ?");
        $stmt->execute([$maxId]);
        $count = $stmt->fetchColumn();

        if ($count === 0) {
            return;
        }

        // 差分レコードのみを取得してインポート
        $query = "
            SELECT
                id,
                open_chat_id,
                created_at,
                type
            FROM
                ban_room
            WHERE id > ?
            ORDER BY id
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->sourceCommentPdo->prepare($query);

        $this->processInChunks(
            $stmt,
            [1 => [$maxId, PDO::PARAM_INT]],
            $count,
            self::CHUNK_SIZE,
            function (array $data) {
                if (!empty($data)) {
                    $this->sqliteInsert('ban_room', $data);
                }
            },
            'ban_room: %d / %d 件処理完了'
        );
    }

    /**
     * ban_userテーブルのインポート（IDベース）
     */
    private function importBanUsers(): void
    {
        // ターゲットDBから最大IDを取得
        $stmt = $this->targetPdo->query("SELECT COALESCE(MAX(id), 0) as max_id FROM ban_user");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxId = (int)$result['max_id'];

        // ソースDBから差分レコード数を取得
        $stmt = $this->sourceCommentPdo->prepare("SELECT COUNT(*) FROM ban_user WHERE id > ?");
        $stmt->execute([$maxId]);
        $count = $stmt->fetchColumn();

        if ($count === 0) {
            return;
        }

        // 差分レコードのみを取得してインポート
        $query = "
            SELECT
                id,
                user_id,
                ip,
                created_at,
                type
            FROM
                ban_user
            WHERE id > ?
            ORDER BY id
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->sourceCommentPdo->prepare($query);

        $this->processInChunks(
            $stmt,
            [1 => [$maxId, PDO::PARAM_INT]],
            $count,
            self::CHUNK_SIZE,
            function (array $data) {
                if (!empty($data)) {
                    $this->sqliteInsert('ban_user', $data);
                }
            },
            'ban_user: %d / %d 件処理完了'
        );
    }

    /**
     * logテーブルのインポート（IDベース）
     */
    private function importCommentLogs(): void
    {
        // ターゲットDBから最大IDを取得
        $stmt = $this->targetPdo->query("SELECT COALESCE(MAX(id), 0) as max_id FROM comment_log");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxId = (int)$result['max_id'];

        // ソースDBから差分レコード数を取得
        $stmt = $this->sourceCommentPdo->prepare("SELECT COUNT(*) FROM log WHERE id > ?");
        $stmt->execute([$maxId]);
        $count = $stmt->fetchColumn();

        if ($count === 0) {
            return;
        }

        // 差分レコードのみを取得してインポート
        $query = "
            SELECT
                id,
                entity_id,
                type,
                data,
                ip,
                ua
            FROM
                log
            WHERE id > ?
            ORDER BY id
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->sourceCommentPdo->prepare($query);

        $this->processInChunks(
            $stmt,
            [1 => [$maxId, PDO::PARAM_INT]],
            $count,
            self::CHUNK_SIZE,
            function (array $data) {
                if (!empty($data)) {
                    $this->sqliteInsert('comment_log', $data);
                }
            },
            'comment_log: %d / %d 件処理完了'
        );
    }

    /**
     * プリペアドステートメントでデータをチャンク単位で処理
     *
     * @param PDOStatement $stmt 実行するプリペアドステートメント
     * @param array $bindParams バインドするパラメータ配列 [position => [value, type]]
     * @param int $totalCount 処理するレコードの総数
     * @param int $chunkSize チャンクサイズ
     * @param callable $processCallback 取得データを処理するコールバック関数
     * @param string|null $progressMessage 進捗メッセージフォーマット（%dで件数を表示）
     */
    private function processInChunks(
        PDOStatement $stmt,
        array $bindParams,
        int $totalCount,
        int $chunkSize,
        callable $processCallback,
        ?string $progressMessage = null
    ): void {
        $processedCount = 0;

        for ($offset = 0; $offset < $totalCount; $offset += $chunkSize) {
            // 静的パラメータをバインド
            foreach ($bindParams as $position => [$value, $type]) {
                $stmt->bindValue($position, $value, $type);
            }

            // 動的パラメータ（LIMIT、OFFSET）をバインド
            $nextPosition = count($bindParams) + 1;
            $stmt->bindValue($nextPosition, $chunkSize, PDO::PARAM_INT);
            $stmt->bindValue($nextPosition + 1, $offset, PDO::PARAM_INT);

            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($data)) {
                $processCallback($data);
                $processedCount += count($data);

                if ($progressMessage !== null) {
                    $this->log(sprintf($progressMessage, $processedCount, $totalCount));
                }
            }
        }
    }

    /**
     * SQLite用INSERTヘルパー（INSERT OR IGNORE）
     *
     * 新規レコードのみを挿入します。
     * 重複がある場合は無視されます（MySQLのINSERT IGNOREと同等）。
     *
     * 高速化のため一括INSERT（複数VALUES句）を使用
     */
    private function sqliteInsert(string $tableName, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $columns = array_keys($data[0]);
        $columnCount = count($columns);

        // 一括INSERT用のVALUES句を生成
        $placeholders = '(' . implode(', ', array_fill(0, $columnCount, '?')) . ')';
        $valuesClause = implode(', ', array_fill(0, count($data), $placeholders));

        $sql = "INSERT OR IGNORE INTO {$tableName} (" . implode(', ', $columns) . ") VALUES {$valuesClause}";

        // 全行のデータを1次元配列にフラット化
        $allValues = [];
        foreach ($data as $row) {
            foreach (array_values($row) as $value) {
                $allValues[] = $value;
            }
        }

        $stmt = $this->targetPdo->prepare($sql);
        $stmt->execute($allValues);
    }

    /**
     * ログメッセージを出力
     *
     * @param string $message 出力するメッセージ
     */
    private function log(string $message): void
    {
        $this->discordNotificationCount++;

        // 初回または100件ごとにDiscord通知を送信
        if ($this->discordNotificationCount % self::DISCORD_NOTIFY_INTERVAL === 0) {
            AdminTool::sendDiscordNotify($message);
        }
    }
}

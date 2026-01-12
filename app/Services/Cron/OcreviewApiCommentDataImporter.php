<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\Services\Admin\AdminTool;
use App\Services\Cron\Utility\CronUtility;
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

    /** 事前計算された差分データ（処理の重複を避けるため） */
    private array $pendingCounts = [];

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

        // 処理開始前に全テーブルのINSERT件数を計算してログ出力
        $this->calculateAndLogPendingCounts();

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
        $schemaPath = \App\Config\AppConfig::SQLITE_SCHEMA_SQLAPI;

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
     * 処理開始前に全テーブルのINSERT予定件数を計算してログ出力
     */
    private function calculateAndLogPendingCounts(): void
    {
        $counts = [];

        $counts = array_merge($counts, $this->calculatePendingCountsForComments());
        $counts = array_merge($counts, $this->calculatePendingCountsForCommentLikes());
        $counts = array_merge($counts, $this->calculatePendingCountsForBanRooms());
        $counts = array_merge($counts, $this->calculatePendingCountsForBanUsers());
        $counts = array_merge($counts, $this->calculatePendingCountsForCommentLogs());

        // ログ出力（1回だけ）
        if (!empty($counts)) {
            CronUtility::addCronLog('コメントアーカイブデータベース インポート予定: ' . implode(', ', $counts));
        } else {
            CronUtility::addCronLog('コメントアーカイブデータベース インポート対象のデータはありません');
        }
    }

    /**
     * comment の差分件数を計算
     *
     * @return array ログ出力用の配列
     */
    private function calculatePendingCountsForComments(): array
    {
        $stmt = $this->targetPdo->query("SELECT COALESCE(MAX(comment_id), 0) as max_id FROM comment");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxId = (int)$result['max_id'];
        $stmt = $this->sourceCommentPdo->prepare("SELECT COUNT(*) FROM comment WHERE comment_id > ?");
        $stmt->execute([$maxId]);
        $count = (int)$stmt->fetchColumn();
        $this->pendingCounts['comment'] = ['max_id' => $maxId, 'count' => $count];

        return $count > 0 ? ["comment: {$count}件"] : [];
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
        // 事前計算した差分データを再利用
        $maxId = $this->pendingCounts['comment']['max_id'];
        $count = $this->pendingCounts['comment']['count'];

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

        // レコード数の整合性を検証し、不一致があれば修正
        $this->verifyAndFixRecordCount(
            'comment',
            'comment',
            'comment_id',
            'comment_id'
        );
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
     *
     * SQLiteのパラメータ数制限（デフォルト999）を考慮してチャンク処理を行います。
     * 1レコードあたり3パラメータ（comment_id × 2 + flag × 1）を使用するため、
     * 999 / 3 = 333が理論上の最大値。安全マージンを考慮して250件ずつ処理します。
     */
    private function bulkUpdateCommentFlags(array $records): void
    {
        if (empty($records)) {
            return;
        }

        // SQLiteのパラメータ数制限を考慮したチャンクサイズ
        $recordsPerBatch = 100;

        // レコードをチャンク単位で処理
        foreach (array_chunk($records, $recordsPerBatch) as $chunk) {
            $this->executeBulkUpdateFlagsBatch($chunk);
        }
    }

    /**
     * バッチ単位でのflag UPDATE実行
     */
    private function executeBulkUpdateFlagsBatch(array $records): void
    {
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
     * comment_like の差分件数を計算
     *
     * @return array ログ出力用の配列
     */
    private function calculatePendingCountsForCommentLikes(): array
    {
        $sourceIds = $this->sourceCommentPdo->query("SELECT id FROM `like` ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $targetIds = $this->targetPdo->query("SELECT id FROM comment_like ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $idsToInsert = array_diff($sourceIds, $targetIds);
        $idsToDelete = array_diff($targetIds, $sourceIds);
        $this->pendingCounts['comment_like'] = [
            'insert_ids' => $idsToInsert,
            'delete_ids' => $idsToDelete
        ];

        if (empty($idsToInsert) && empty($idsToDelete)) {
            return [];
        }
        return ["comment_like: 追加" . count($idsToInsert) . "件/削除" . count($idsToDelete) . "件"];
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
        // 事前計算した差分データを再利用
        $idsToInsert = $this->pendingCounts['comment_like']['insert_ids'];
        $idsToDelete = $this->pendingCounts['comment_like']['delete_ids'];

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
        }
    }

    /**
     * ban_room の差分件数を計算
     *
     * @return array ログ出力用の配列
     */
    private function calculatePendingCountsForBanRooms(): array
    {
        $sourceIds = $this->sourceCommentPdo->query("SELECT id FROM ban_room ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $targetIds = $this->targetPdo->query("SELECT id FROM ban_room ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $idsToInsert = array_diff($sourceIds, $targetIds);
        $idsToDelete = array_diff($targetIds, $sourceIds);
        $this->pendingCounts['ban_room'] = [
            'insert_ids' => $idsToInsert,
            'delete_ids' => $idsToDelete
        ];

        if (empty($idsToInsert) && empty($idsToDelete)) {
            return [];
        }
        return ["ban_room: 追加" . count($idsToInsert) . "件/削除" . count($idsToDelete) . "件"];
    }

    /**
     * ban_roomテーブルのインポート（完全同期）
     *
     * 【完全同期の仕組み】
     * BANは解除される可能性があるため、削除も反映する必要がある。
     * そのため、ソースとターゲットのID配列を比較して差分を適用する。
     * - ソースに存在しターゲットに存在しないID: 追加
     * - ターゲットに存在しソースに存在しないID: 削除
     */
    private function importBanRooms(): void
    {
        // 事前計算した差分データを再利用
        $idsToInsert = $this->pendingCounts['ban_room']['insert_ids'];
        $idsToDelete = $this->pendingCounts['ban_room']['delete_ids'];

        // 追加すべきレコードをインポート
        if (!empty($idsToInsert)) {
            $chunks = array_chunk($idsToInsert, self::CHUNK_SIZE);

            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $query = "SELECT id, open_chat_id, created_at, type FROM ban_room WHERE id IN ($placeholders)";
                $stmt = $this->sourceCommentPdo->prepare($query);
                $stmt->execute($chunk);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($data)) {
                    $this->sqliteInsert('ban_room', $data);
                }
            }
        }

        // 削除すべきレコードを削除
        if (!empty($idsToDelete)) {
            $chunks = array_chunk($idsToDelete, self::CHUNK_SIZE);

            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $query = "DELETE FROM ban_room WHERE id IN ($placeholders)";
                $stmt = $this->targetPdo->prepare($query);
                $stmt->execute($chunk);
            }
        }
    }

    /**
     * ban_user の差分件数を計算
     *
     * @return array ログ出力用の配列
     */
    private function calculatePendingCountsForBanUsers(): array
    {
        $sourceIds = $this->sourceCommentPdo->query("SELECT id FROM ban_user ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $targetIds = $this->targetPdo->query("SELECT id FROM ban_user ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $idsToInsert = array_diff($sourceIds, $targetIds);
        $idsToDelete = array_diff($targetIds, $sourceIds);
        $this->pendingCounts['ban_user'] = [
            'insert_ids' => $idsToInsert,
            'delete_ids' => $idsToDelete
        ];

        if (empty($idsToInsert) && empty($idsToDelete)) {
            return [];
        }
        return ["ban_user: 追加" . count($idsToInsert) . "件/削除" . count($idsToDelete) . "件"];
    }

    /**
     * ban_userテーブルのインポート（完全同期）
     *
     * 【完全同期の仕組み】
     * BANは解除される可能性があるため、削除も反映する必要がある。
     * そのため、ソースとターゲットのID配列を比較して差分を適用する。
     * - ソースに存在しターゲットに存在しないID: 追加
     * - ターゲットに存在しソースに存在しないID: 削除
     */
    private function importBanUsers(): void
    {
        // 事前計算した差分データを再利用
        $idsToInsert = $this->pendingCounts['ban_user']['insert_ids'];
        $idsToDelete = $this->pendingCounts['ban_user']['delete_ids'];

        // 追加すべきレコードをインポート
        if (!empty($idsToInsert)) {
            $chunks = array_chunk($idsToInsert, self::CHUNK_SIZE);

            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $query = "SELECT id, user_id, ip, created_at, type FROM ban_user WHERE id IN ($placeholders)";
                $stmt = $this->sourceCommentPdo->prepare($query);
                $stmt->execute($chunk);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($data)) {
                    $this->sqliteInsert('ban_user', $data);
                }
            }
        }

        // 削除すべきレコードを削除
        if (!empty($idsToDelete)) {
            $chunks = array_chunk($idsToDelete, self::CHUNK_SIZE);

            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $query = "DELETE FROM ban_user WHERE id IN ($placeholders)";
                $stmt = $this->targetPdo->prepare($query);
                $stmt->execute($chunk);
            }
        }
    }

    /**
     * comment_log の差分件数を計算
     *
     * @return array ログ出力用の配列
     */
    private function calculatePendingCountsForCommentLogs(): array
    {
        $stmt = $this->targetPdo->query("SELECT COALESCE(MAX(id), 0) as max_id FROM comment_log");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxId = (int)$result['max_id'];
        $stmt = $this->sourceCommentPdo->prepare("SELECT COUNT(*) FROM log WHERE id > ?");
        $stmt->execute([$maxId]);
        $count = (int)$stmt->fetchColumn();
        $this->pendingCounts['comment_log'] = ['max_id' => $maxId, 'count' => $count];

        return $count > 0 ? ["comment_log: {$count}件"] : [];
    }

    /**
     * logテーブルのインポート（IDベース）
     */
    private function importCommentLogs(): void
    {
        // 事前計算した差分データを再利用
        $maxId = $this->pendingCounts['comment_log']['max_id'];
        $count = $this->pendingCounts['comment_log']['count'];

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

        // レコード数の整合性を検証し、不一致があれば修正
        $this->verifyAndFixRecordCount(
            'log',
            'comment_log',
            'id',
            'id'
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
     * SQLiteのパラメータ数制限（デフォルト999）を考慮してチャンク処理を行います。
     */
    private function sqliteInsert(string $tableName, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $columns = array_keys($data[0]);
        $columnCount = count($columns);

        // SQLiteのパラメータ数制限を考慮したチャンクサイズ
        // commentテーブル（8カラム）の場合: 999 / 8 = 124が理論上の最大値
        // 安全マージンを考慮して、999 / カラム数 - 20 で計算
        $recordsPerBatch = (int)floor(999 / $columnCount) - 20;

        // データをチャンク単位で処理
        foreach (array_chunk($data, $recordsPerBatch) as $chunk) {
            $this->executeSqliteInsertBatch($tableName, $chunk, $columns, $columnCount);
        }
    }

    /**
     * SQLite INSERT のバッチ実行
     */
    private function executeSqliteInsertBatch(string $tableName, array $chunk, array $columns, int $columnCount): void
    {
        // 一括INSERT用のVALUES句を生成
        $placeholders = '(' . implode(', ', array_fill(0, $columnCount, '?')) . ')';
        $valuesClause = implode(', ', array_fill(0, count($chunk), $placeholders));

        $sql = "INSERT OR IGNORE INTO {$tableName} (" . implode(', ', $columns) . ") VALUES {$valuesClause}";

        // 全行のデータを1次元配列にフラット化
        $allValues = [];
        foreach ($chunk as $row) {
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

    /**
     * ソースの全レコードがターゲットに存在するか検証し、不足があれば修正
     *
     * アーカイブ用DBなので、ターゲット側はデータを削除しません。
     * そのため、ターゲット ≧ ソースが正常な状態です。
     * ソースに存在してターゲットに存在しないレコードがあれば、それを挿入します。
     *
     * @param string $sourceTable ソーステーブル名
     * @param string $targetTable ターゲットテーブル名
     * @param string $sourceIdColumn ソースのIDカラム名
     * @param string $targetIdColumn ターゲットのIDカラム名
     * @param callable|null $transformCallback データ変換コールバック (null の場合は変換なし)
     */
    private function verifyAndFixRecordCount(
        string $sourceTable,
        string $targetTable,
        string $sourceIdColumn,
        string $targetIdColumn,
        ?callable $transformCallback = null
    ): void {
        // ソースとターゲットの全IDを100件ずつ取得して差分を計算
        $missingIds = $this->findMissingIds($sourceTable, $targetTable, $sourceIdColumn, $targetIdColumn);

        if (empty($missingIds)) {
            // 差分なし（全てのソースレコードがターゲットに存在する）
            return;
        }

        // レコード数を取得（ログ用）
        $sourceCount = $this->sourceCommentPdo->query("SELECT COUNT(*) FROM {$sourceTable}")->fetchColumn();
        $targetCount = $this->targetPdo->query("SELECT COUNT(*) FROM {$targetTable}")->fetchColumn();

        AdminTool::sendDiscordNotify(sprintf(
            '【%s】不足レコード検出: %d 件のソースレコードがターゲットに存在しません (ソース: %d, ターゲット: %d)',
            $targetTable,
            count($missingIds),
            $sourceCount,
            $targetCount
        ));

        // 不足しているレコードを100件ずつチャンクで取得・挿入
        $this->insertMissingRecords($sourceTable, $targetTable, $sourceIdColumn, $missingIds, $transformCallback);

        AdminTool::sendDiscordNotify(sprintf('【%s】不足レコード挿入完了: %d 件', $targetTable, count($missingIds)));
    }

    /**
     * ソースとターゲット間で不足しているIDを検出
     *
     * @return array 不足しているIDの配列
     */
    private function findMissingIds(
        string $sourceTable,
        string $targetTable,
        string $sourceIdColumn,
        string $targetIdColumn
    ): array {
        // ターゲットの全IDを100件ずつ取得
        $targetIds = $this->fetchAllIdsInChunks($this->targetPdo, $targetTable, $targetIdColumn);

        // ソースの全IDを100件ずつ取得
        $sourceIds = $this->fetchAllIdsInChunks($this->sourceCommentPdo, $sourceTable, $sourceIdColumn);

        // 差分を計算（ソースに存在し、ターゲットに存在しないID）
        return array_diff($sourceIds, $targetIds);
    }

    /**
     * 指定されたテーブルから全IDを100件ずつチャンクで取得
     *
     * @return array IDの配列
     */
    private function fetchAllIdsInChunks(
        PDO $pdo,
        string $tableName,
        string $idColumn
    ): array {
        $allIds = [];
        $chunkSize = 100;
        $offset = 0;

        // 全体のレコード数を取得
        $totalCount = $pdo->query("SELECT COUNT(*) FROM {$tableName}")->fetchColumn();

        if ($totalCount === 0) {
            return [];
        }

        // 100件ずつ取得
        while ($offset < $totalCount) {
            $query = "SELECT {$idColumn} FROM {$tableName} ORDER BY {$idColumn} LIMIT {$chunkSize} OFFSET {$offset}";
            $stmt = $pdo->query($query);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $allIds = array_merge($allIds, $ids);
            $offset += $chunkSize;
        }

        return $allIds;
    }

    /**
     * 不足しているレコードを100件ずつチャンクで取得・挿入
     */
    private function insertMissingRecords(
        string $sourceTable,
        string $targetTable,
        string $sourceIdColumn,
        array $missingIds,
        ?callable $transformCallback
    ): void {
        $chunkSize = 100;

        foreach (array_chunk($missingIds, $chunkSize) as $chunk) {
            // ソースDBから不足しているレコードを取得
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $query = "SELECT * FROM {$sourceTable} WHERE {$sourceIdColumn} IN ({$placeholders})";
            $stmt = $this->sourceCommentPdo->prepare($query);
            $stmt->execute($chunk);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($records)) {
                continue;
            }

            // データ変換が必要な場合は変換
            if ($transformCallback !== null) {
                $transformedRecords = [];
                foreach ($records as $record) {
                    $transformedRecords[] = $transformCallback($record);
                }
                $records = $transformedRecords;
            }

            // ターゲットDBに挿入
            $this->sqliteInsert($targetTable, $records);
        }
    }
}

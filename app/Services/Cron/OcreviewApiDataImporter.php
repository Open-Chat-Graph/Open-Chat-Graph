<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\Config\AppConfig;
use App\Models\Importer\SqlInsert;
use App\Models\SQLite\SQLiteStatistics;
use App\Models\SQLite\SQLiteRankingPosition;
use App\Models\SQLite\SQLiteOcgraphSqlapi;
use App\Services\Admin\AdminTool;
use PDO;
use PDOStatement;
use Shared\MimimalCmsConfig;

/**
 * ocgraph_sqlapi データベース（SQLite）へのデータインポートサービス
 * 
 * docker compose exec app ./vendor/bin/phpunit app/Services/Cron/test/OcreviewApiDataImporterUpsertTest.php
 *　
 * 【実行頻度】毎時実行を想定
 *
 * 【差分同期の仕組み】
 * このインポーターは、実行が中断されても自動的に差分を検知して最新化できる設計になっています。
 * 各テーブルのインポート処理は、前回の最終取り込み時点を記録し、その時点以降の差分のみを取得します。
 * そのため、数時間～数日間実行が停止していても、次回実行時に自動的に未取り込みデータを全て取り込み、
 * データベースを最新状態に復元できます。
 *
 * 【データソース】
 * - ソースDB: MySQL ocgraph_ocreview（メインデータベース）
 * - ソースDB: SQLite statistics（統計データ）
 * - ソースDB: SQLite ranking_position（ランキング履歴データ）
 * - ターゲットDB: SQLite ocgraph_sqlapi（API公開用データベース）
 */
class OcreviewApiDataImporter
{
    protected PDO $targetPdo;
    protected PDO $sourcePdo;
    protected PDO $sqliteStatisticsPdo;
    protected PDO $sqliteRankingPositionPdo;

    /** Discord通知カウンター */
    private int $discordNotificationCount = 0;

    /** Discord通知間隔（何件処理するごとに通知するか） */
    private const DISCORD_NOTIFY_INTERVAL = 100;

    /** チャンクサイズ（MySQL一括処理） */
    private const CHUNK_SIZE = 2000;

    /** チャンクサイズ（SQLite一括処理） */
    private const CHUNK_SIZE_SQLITE = 10000;

    public function __construct(
        private SqlInsert $sqlImporter,
    ) {}

    /**
     * 全データインポート処理を実行
     *
     * 各テーブルのインポート処理を順次実行します。
     * すべての処理は差分同期対応しているため、実行が中断されても次回実行時に自動復旧します。
     */
    public function execute(): void
    {
        $this->initializeConnections();

        // オープンチャットマスターデータのインポート（差分同期）
        $this->importOpenChatMaster();

        // 成長ランキングのインポート（全件リフレッシュ）
        $this->importGrowthRankings();

        // 日次メンバー統計のインポート（差分同期）
        $this->importDailyMemberStatistics();

        // LINE公式アクティビティ履歴のインポート（差分同期）
        $this->importLineOfficialActivityHistory();

        // LINE公式ランキング総数のインポート（差分同期）
        $this->importTotalCount();

        // カテゴリマスターのインポート（全件リフレッシュ）
        $this->importCategories();

        // 削除されたオープンチャット履歴のインポート（差分同期）
        $this->importOpenChatDeleted();
    }

    /**
     * データベース接続を初期化
     *
     * テスト時にオーバーライド可能にするためprotectedに変更
     */
    protected function initializeConnections(): void
    {
        // ソースデータベース（MySQL: ocgraph_ocreview）に接続
        $this->sourcePdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', MimimalCmsConfig::$dbHost, 'ocgraph_ocreview'),
            MimimalCmsConfig::$dbUserName,
            MimimalCmsConfig::$dbPassword,
            [
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION TRANSACTION READ ONLY"
            ]
        );

        // ターゲットデータベース（SQLite: ocgraph_sqlapi）に接続
        $this->targetPdo = SQLiteOcgraphSqlapi::connect();

        // 統計データベース（SQLite: statistics）に読み取り専用で接続
        $this->sqliteStatisticsPdo = SQLiteStatistics::connect([
            'mode' => '?mode=ro'
        ]);

        // ランキング履歴データベース（SQLite: ranking_position）に読み取り専用で接続
        $this->sqliteRankingPositionPdo = SQLiteRankingPosition::connect([
            'mode' => '?mode=ro'
        ]);
    }

    /**
     * オープンチャットマスターデータのインポート
     *
     * 【差分同期の仕組み】
     * ターゲットDBの last_updated_at の最大値を取得し、それ以降に更新されたレコードのみをソースから取得。
     * 実行が中断されていても、前回の最終更新時点から自動的に差分を取り込みます。
     *
     * さらに、updated_at が更新されていなくてもメンバー数が変更されている場合は
     * syncMemberCountDifferences() で同期します。
     *
     * テスト時にアクセス可能にするためprotectedに変更
     */
    protected function importOpenChatMaster(): void
    {
        // ターゲットDBから最終更新日時を取得（差分同期の起点）
        $stmt = $this->targetPdo->query("SELECT MAX(last_updated_at) as max_updated FROM openchat_master");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastUpdated = $result['max_updated'] ?? '1970-01-01 00:00:00';

        // ソースDBから差分レコード数を取得
        $countQuery = "SELECT COUNT(*) FROM open_chat WHERE updated_at >= ?";
        $countStmt = $this->sourcePdo->prepare($countQuery);
        $countStmt->execute([$lastUpdated]);
        $totalCount = $countStmt->fetchColumn();

        if ($totalCount === 0) {
            // updated_at が更新されていない場合でも、メンバー数の差分をチェック
            $this->syncMemberCountDifferences();
            return;
        }

        // 差分レコードのみを取得
        $query = "
            SELECT
                id,
                emid,
                name,
                url,
                description,
                img_url,
                member,
                emblem,
                category,
                join_method_type,
                api_created_at,
                created_at,
                updated_at
            FROM open_chat
            WHERE updated_at >= ?
            ORDER BY id
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->sourcePdo->prepare($query);

        $this->processInChunks(
            $stmt,
            [1 => [$lastUpdated, PDO::PARAM_STR]],
            $totalCount,
            self::CHUNK_SIZE,
            function (array $rows) {
                $data = [];
                foreach ($rows as $row) {
                    $data[] = $this->transformOpenChatRow($row);
                }

                if (!empty($data)) {
                    $this->targetPdo->beginTransaction();
                    try {
                        // SQLiteのUPSERT（INSERT OR REPLACE）でデータを更新
                        $this->sqliteUpsert('openchat_master', $data);
                        $this->targetPdo->commit();
                    } catch (\Exception $e) {
                        $this->targetPdo->rollBack();
                        throw $e;
                    }
                }
            },
        );

        // updated_at が更新されていないメンバー数の変更も同期
        $this->syncMemberCountDifferences();
    }

    /**
     * ソースDBのopen_chatレコードをターゲットDBのopenchat_master形式に変換
     */
    private function transformOpenChatRow(array $row): array
    {
        return [
            'openchat_id' => $row['id'],
            'line_internal_id' => $row['emid'],
            'display_name' => $row['name'],
            'invitation_url' => $row['url'],
            'description' => $row['description'],
            'profile_image_url' => $row['img_url'],
            'current_member_count' => $row['member'],
            'verification_badge' => $this->convertEmblem($row['emblem']),
            'category_id' => $row['category'],
            'join_method' => $this->convertJoinMethod($row['join_method_type']),
            'established_at' => $this->convertUnixTimeToDatetime($row['api_created_at']),
            'first_seen_at' => $row['created_at'],
            'last_updated_at' => $row['updated_at'],
        ];
    }

    /**
     * エンブレム値を認証バッジテキストに変換
     */
    private function convertEmblem(?int $emblem): ?string
    {
        return match ($emblem) {
            1 => 'スペシャル',
            2 => '公式認証',
            default => null,
        };
    }

    /**
     * 参加方法タイプを日本語テキストに変換
     */
    private function convertJoinMethod(int $joinMethodType): string
    {
        return match ($joinMethodType) {
            0 => '全体公開',
            1 => '参加承認制',
            2 => '参加コード入力制',
            default => '全体公開',
        };
    }

    /**
     * Unixタイムスタンプを日時文字列に変換
     */
    private function convertUnixTimeToDatetime(?int $unixTime): ?string
    {
        if ($unixTime === null || $unixTime === 0) {
            return null;
        }
        return date('Y-m-d H:i:s', $unixTime);
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
     * ログメッセージを出力（開発環境では標準出力、本番環境ではDiscord通知）
     *
     * @param string $message 出力するメッセージ
     */
    private function log(string $message): void
    {
        if (AppConfig::$isDevlopment) {
            echo $message . "\n";
        } else {
            $this->discordNotificationCount++;

            // 初回または100件ごとにDiscord通知を送信
            if ($this->discordNotificationCount % self::DISCORD_NOTIFY_INTERVAL === 0) {
                AdminTool::sendDiscordNotify($message);
            }
        }
    }

    /**
     * 成長ランキングのインポート（1時間、24時間、1週間）
     *
     * 【差分同期の仕組み】
     * このテーブルは差分同期ではなく、毎回全件リフレッシュします。
     * ソースDBのランキングデータは常に最新の全順位を保持しているため、
     * 全削除→全挿入で最新状態に更新します。
     */
    private function importGrowthRankings(): void
    {
        $rankings = [
            'statistics_ranking_hour' => 'growth_ranking_past_hour',
            'statistics_ranking_hour24' => 'growth_ranking_past_24_hours',
            'statistics_ranking_week' => 'growth_ranking_past_week',
        ];

        foreach ($rankings as $sourceTable => $targetTable) {
            // ソーステーブルのレコード数を取得
            $countQuery = "SELECT COUNT(*) FROM $sourceTable";
            $totalCount = $this->sourcePdo->query($countQuery)->fetchColumn();

            if ($totalCount === 0) {
                continue;
            }

            // ターゲットテーブルを全削除（SQLiteはTRUNCATEをサポートしていないため DELETE を使用）
            $this->targetPdo->exec("DELETE FROM $targetTable");

            // ソーステーブルから全データを取得
            $query = "
                SELECT
                    id as ranking_position,
                    open_chat_id as openchat_id,
                    diff_member as member_increase_count,
                    percent_increase as growth_rate_percent
                FROM
                    $sourceTable
                ORDER BY id
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->sourcePdo->prepare($query);

            $this->processInChunks(
                $stmt,
                [],
                $totalCount,
                self::CHUNK_SIZE,
                function (array $data) use ($targetTable) {
                    if (!empty($data)) {
                        $this->targetPdo->beginTransaction();
                        try {
                            $this->sqlImporter->import($this->targetPdo, $targetTable, $data, self::CHUNK_SIZE);
                            $this->targetPdo->commit();
                        } catch (\Exception $e) {
                            $this->targetPdo->rollBack();
                            throw $e;
                        }
                    }
                },
            );
        }
    }

    /**
     * 日次メンバー統計のインポート
     *
     * 【差分同期の仕組み】
     * ターゲットDBの record_id の最大値を取得し、それより大きいIDのレコードのみをソースから取得。
     * 実行が中断されても、前回の最終取り込みID以降のレコードを自動的に取り込みます。
     */
    private function importDailyMemberStatistics(): void
    {
        // ターゲットDBから最大レコードIDを取得（差分同期の起点）
        $stmt = $this->targetPdo->query("SELECT COALESCE(MAX(record_id), 0) as max_id FROM daily_member_statistics");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxId = (int)$result['max_id'];

        // ソースDBから差分レコード数を取得
        $stmt = $this->sqliteStatisticsPdo->prepare("SELECT count(*) FROM statistics WHERE id > ?");
        $stmt->execute([$maxId]);
        $count = $stmt->fetchColumn();

        if ($count === 0) {
            return;
        }

        // 差分レコードのみを取得
        $query = "
            SELECT
                id as record_id,
                open_chat_id as openchat_id,
                member as member_count,
                date as statistics_date
            FROM
                statistics
            WHERE id > ?
            ORDER BY id
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->sqliteStatisticsPdo->prepare($query);

        $this->processInChunks(
            $stmt,
            [1 => [$maxId, PDO::PARAM_INT]],
            $count,
            self::CHUNK_SIZE_SQLITE,
            function (array $data) {
                if (!empty($data)) {
                    $this->targetPdo->beginTransaction();
                    try {
                        $this->sqlImporter->import($this->targetPdo, 'daily_member_statistics', $data, self::CHUNK_SIZE_SQLITE);
                        $this->targetPdo->commit();
                    } catch (\Exception $e) {
                        $this->targetPdo->rollBack();
                        throw $e;
                    }
                }
            },
            'daily_member_statistics: %d / %d 件処理完了'
        );
    }

    /**
     * LINE公式ランキング総数のインポート
     *
     * 【差分同期の仕組み】
     * ターゲットDBの record_id の最大値を取得し、それより大きいIDのレコードのみをソースから取得。
     * 実行が中断されても、前回の最終取り込みID以降のレコードを自動的に取り込みます。
     */
    private function importTotalCount(): void
    {
        // ターゲットDBから最大レコードIDを取得（差分同期の起点）
        $stmt = $this->targetPdo->query("SELECT COALESCE(MAX(record_id), 0) as max_id FROM line_official_ranking_total_count");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxId = (int)$result['max_id'];

        // ソースDBから差分レコード数を取得
        $stmt = $this->sqliteRankingPositionPdo->prepare("SELECT count(*) FROM total_count WHERE id > ?");
        $stmt->execute([$maxId]);
        $count = $stmt->fetchColumn();

        if ($count === 0) {
            return;
        }

        // 差分レコードのみを取得
        $query = "
            SELECT
                id as record_id,
                total_count_rising as activity_trending_total_count,
                total_count_ranking as activity_ranking_total_count,
                time as recorded_at,
                category as category_id
            FROM
                total_count
            WHERE id > ?
            ORDER BY id
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->sqliteRankingPositionPdo->prepare($query);

        $this->processInChunks(
            $stmt,
            [1 => [$maxId, PDO::PARAM_INT]],
            $count,
            self::CHUNK_SIZE_SQLITE,
            function (array $data) {
                if (!empty($data)) {
                    $this->targetPdo->beginTransaction();
                    try {
                        $this->sqlImporter->import($this->targetPdo, 'line_official_ranking_total_count', $data, self::CHUNK_SIZE_SQLITE);
                        $this->targetPdo->commit();
                    } catch (\Exception $e) {
                        $this->targetPdo->rollBack();
                        throw $e;
                    }
                }
            },
            'line_official_ranking_total_count: %d / %d 件処理完了'
        );
    }

    /**
     * LINE公式アクティビティ履歴のインポート（ランキング・急上昇）
     *
     * 【差分同期の仕組み】
     * ターゲットDBの record_id の最大値を取得し、それより大きいIDのレコードのみをソースから取得。
     * 実行が中断されても、前回の最終取り込みID以降のレコードを自動的に取り込みます。
     */
    private function importLineOfficialActivityHistory(): void
    {
        $tables = [
            'ranking' => 'line_official_activity_ranking_history',
            'rising' => 'line_official_activity_trending_history',
        ];

        foreach ($tables as $sourceTable => $targetTable) {
            // ターゲットDBから最大レコードIDを取得（差分同期の起点）
            $stmt = $this->targetPdo->query("SELECT COALESCE(MAX(record_id), 0) as max_id FROM $targetTable");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $maxId = (int)$result['max_id'];

            // ソースDBから差分レコード数を取得
            $stmt = $this->sqliteRankingPositionPdo->prepare("SELECT count(*) FROM {$sourceTable} WHERE id > ?");
            $stmt->execute([$maxId]);
            $count = $stmt->fetchColumn();

            if ($count === 0) {
                continue;
            }

            $positionColumn = $sourceTable === 'ranking' ? 'activity_ranking_position' : 'activity_trending_position';

            // 差分レコードのみを取得
            $query = "
                SELECT
                    id as record_id,
                    open_chat_id as openchat_id,
                    category as category_id,
                    position as {$positionColumn},
                    strftime('%Y-%m-%d %H:%M:%S', time) as recorded_at,
                    strftime('%Y-%m-%d', date) as record_date
                FROM
                    {$sourceTable}
                WHERE id > ?
                ORDER BY id
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->sqliteRankingPositionPdo->prepare($query);

            $this->processInChunks(
                $stmt,
                [1 => [$maxId, PDO::PARAM_INT]],
                $count,
                self::CHUNK_SIZE_SQLITE,
                function (array $data) use ($targetTable) {
                    if (!empty($data)) {
                        $this->targetPdo->beginTransaction();
                        try {
                            $this->sqlImporter->import($this->targetPdo, $targetTable, $data, self::CHUNK_SIZE_SQLITE);
                            $this->targetPdo->commit();
                        } catch (\Exception $e) {
                            $this->targetPdo->rollBack();
                            throw $e;
                        }
                    }
                },
                "$targetTable: %d / %d 件処理完了"
            );
        }
    }

    /**
     * ソースとターゲット間のメンバー数差分を同期
     *
     * updated_at が更新されていなくてもメンバー数のみが変更されているケースを検知して同期します。
     *
     * 【差分同期の仕組み】
     * ターゲットDB内の全レコードとソースDBのメンバー数を比較し、差分があるレコードのみを更新。
     * この処理により、updated_at が更新されなかったメンバー数の変更も漏れなく同期されます。
     */
    private function syncMemberCountDifferences(): void
    {
        // ターゲットDB内の全レコードを取得（比較用）
        $targetData = $this->getAllTargetRecords();

        if (empty($targetData)) {
            return;
        }

        // 効率的な比較のために連想配列に変換
        $targetLookup = [];
        foreach ($targetData as $record) {
            $targetLookup[$record['openchat_id']] = $record['current_member_count'];
        }

        // ソースDBの総レコード数を取得
        $totalCount = $this->sourcePdo->query("SELECT COUNT(*) FROM open_chat")->fetchColumn();

        if ($totalCount === 0) {
            return;
        }

        // ソースレコードをチャンク単位で処理し、差分を検出
        $query = "
            SELECT id, member
            FROM open_chat
            ORDER BY id
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->sourcePdo->prepare($query);

        $this->processInChunks(
            $stmt,
            [],
            $totalCount,
            self::CHUNK_SIZE,
            function (array $rows) use ($targetLookup) {
                $updatesNeeded = [];

                foreach ($rows as $row) {
                    $openchatId = $row['id'];

                    // ターゲットに存在し、メンバー数が異なるレコードを抽出
                    if (isset($targetLookup[$openchatId])) {
                        $targetMemberCount = $targetLookup[$openchatId];

                        // メンバー数の差分をチェック
                        if ($row['member'] !== $targetMemberCount) {
                            $updatesNeeded[] = $row;
                        }
                    }
                }

                if (!empty($updatesNeeded)) {
                    $this->bulkUpdateTargetRecordsSqlite($updatesNeeded);
                }
            },
        );
    }

    /**
     * ターゲットDB内の全レコードを取得（比較用）
     */
    private function getAllTargetRecords(): array
    {
        $query = "SELECT openchat_id, current_member_count FROM openchat_master";
        $stmt = $this->targetPdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ターゲットDBのレコードを一括更新（SQLite版）
     */
    private function bulkUpdateTargetRecordsSqlite(array $records): void
    {
        if (empty($records)) {
            return;
        }

        $this->targetPdo->beginTransaction();
        try {
            $stmt = $this->targetPdo->prepare(
                "UPDATE openchat_master SET current_member_count = ? WHERE openchat_id = ?"
            );

            foreach ($records as $record) {
                $stmt->execute([$record['member'], $record['id']]);
            }

            $this->targetPdo->commit();
        } catch (\Exception $e) {
            $this->targetPdo->rollBack();
            throw $e;
        }
    }

    /**
     * SQLite用UPSERTヘルパー（INSERT ... ON CONFLICT ... DO UPDATE）
     *
     * MySQLの ON DUPLICATE KEY UPDATE と同等の動作をSQLiteで実現します。
     * INSERT OR REPLACE と異なり、既存レコードを削除せずに更新します。
     *
     * テスト時にアクセス可能にするためprotectedに変更
     */
    protected function sqliteUpsert(string $tableName, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $columns = array_keys($data[0]);
        $placeholders = array_fill(0, count($columns), '?');

        // openchat_master テーブルの主キーは openchat_id
        $primaryKey = 'openchat_id';

        // UPDATE句を生成（主キー以外のカラムを更新）
        $updateClauses = [];
        foreach ($columns as $column) {
            if ($column !== $primaryKey) {
                $updateClauses[] = "{$column} = excluded.{$column}";
            }
        }

        $sql = "INSERT INTO {$tableName} (" . implode(', ', $columns) . ") " .
               "VALUES (" . implode(', ', $placeholders) . ") " .
               "ON CONFLICT({$primaryKey}) DO UPDATE SET " . implode(', ', $updateClauses);

        $stmt = $this->targetPdo->prepare($sql);

        foreach ($data as $row) {
            $values = array_values($row);
            $stmt->execute($values);
        }
    }

    /**
     * カテゴリマスターのインポート
     *
     * 【差分同期の仕組み】
     * このテーブルは参照データのため、差分同期ではなく毎回全件リフレッシュします。
     * カテゴリデータは追加・変更頻度が低いため、全削除→全挿入で最新状態に更新します。
     */
    private function importCategories(): void
    {
        // ソーステーブルのレコード数を取得
        $countQuery = "SELECT COUNT(*) FROM category";
        $totalCount = $this->sourcePdo->query($countQuery)->fetchColumn();

        if ($totalCount === 0) {
            return;
        }

        // ターゲットテーブルを全削除
        $this->targetPdo->exec("DELETE FROM categories");

        // ソーステーブルから全カテゴリを取得
        $query = "
            SELECT
                id as category_id,
                category as category_name
            FROM
                category
            ORDER BY id
        ";

        $stmt = $this->sourcePdo->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($data)) {
            $this->targetPdo->beginTransaction();
            try {
                $this->sqlImporter->import($this->targetPdo, 'categories', $data, count($data));
                $this->targetPdo->commit();
            } catch (\Exception $e) {
                $this->targetPdo->rollBack();
                throw $e;
            }
        }
    }

    /**
     * 削除されたオープンチャット履歴のインポート
     *
     * 【差分同期の仕組み】
     * ターゲットDBの deleted_at の最大値を取得し、それ以降に削除されたレコードのみをソースから取得。
     * 実行が中断されても、前回の最終取り込み時点以降の削除レコードを自動的に取り込みます。
     *
     * 【idカラムについて】
     * open_chat_deleted.id は AUTO_INCREMENT だが、実際には openchat_id の値が明示的に挿入されています。
     * このため、ApiDeletedOpenChatListRepository では om.openchat_id = ocd.id でJOINできます。
     */
    private function importOpenChatDeleted(): void
    {
        // ターゲットDBから最終削除日時を取得（差分同期の起点）
        $stmt = $this->targetPdo->query("SELECT MAX(deleted_at) as max_deleted FROM open_chat_deleted");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxDeleted = $result['max_deleted'] ?? '1970-01-01 00:00:00';

        // ソースDBから差分レコード数を取得
        $countQuery = "SELECT COUNT(*) FROM open_chat_deleted WHERE deleted_at > ?";
        $countStmt = $this->sourcePdo->prepare($countQuery);
        $countStmt->execute([$maxDeleted]);
        $totalCount = $countStmt->fetchColumn();

        if ($totalCount === 0) {
            return;
        }

        // 差分レコードのみを取得
        $query = "
            SELECT
                id,
                emid,
                deleted_at
            FROM
                open_chat_deleted
            WHERE deleted_at > ?
            ORDER BY id
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->sourcePdo->prepare($query);

        $this->processInChunks(
            $stmt,
            [1 => [$maxDeleted, PDO::PARAM_STR]],
            $totalCount,
            self::CHUNK_SIZE,
            function (array $data) {
                if (!empty($data)) {
                    $this->targetPdo->beginTransaction();
                    try {
                        $this->sqlImporter->import($this->targetPdo, 'open_chat_deleted', $data, self::CHUNK_SIZE);
                        $this->targetPdo->commit();
                    } catch (\Exception $e) {
                        $this->targetPdo->rollBack();
                        throw $e;
                    }
                }
            },
            'open_chat_deleted: %d / %d 件処理完了'
        );
    }

}

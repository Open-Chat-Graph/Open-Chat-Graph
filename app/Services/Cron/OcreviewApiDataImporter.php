<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\Models\CommentRepositories\CommentDB;
use App\Models\Repositories\DB;
use App\Models\SQLite\SQLiteInsertImporter;
use App\Models\SQLite\SQLiteStatistics;
use App\Models\SQLite\SQLiteRankingPosition;
use App\Models\SQLite\SQLiteOcgraphSqlapi;
use App\Services\Admin\AdminTool;
use PDO;
use PDOStatement;

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
    protected PDO $sourceCommentPdo;
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

    /** 事前計算された差分データ（処理の重複を避けるため） */
    private array $pendingCounts = [];

    public function __construct(
        private SQLiteInsertImporter $sqlImporter,
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

        // 処理開始前に全テーブルのINSERT件数を計算してログ出力
        $this->calculateAndLogPendingCounts();

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

        // 削除されたオープンチャット履歴のインポート（差分同期）
        $this->importOpenChatDeleted();

        // コメント関連データのインポート（差分同期）
        $commentImporter = new OcreviewApiCommentDataImporter($this->sourceCommentPdo, $this->targetPdo);
        $commentImporter->execute();
    }

    /**
     * データベース接続を初期化
     */
    protected function initializeConnections(): void
    {
        // ソースデータベース（MySQL: ocgraph_ocreview）に接続
        $this->sourcePdo = DB::connect();

        // ソースデータベース（MySQL: ocgraph_comment）に接続
        $this->sourceCommentPdo = CommentDB::connect();

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
     * 処理開始前に全テーブルのINSERT予定件数を計算してログ出力
     */
    private function calculateAndLogPendingCounts(): void
    {
        $counts = [];

        $counts = array_merge($counts, $this->calculatePendingCountsForOpenChatMaster());
        $counts = array_merge($counts, $this->calculatePendingCountsForGrowthRankings());
        $counts = array_merge($counts, $this->calculatePendingCountsForDailyMemberStatistics());
        $counts = array_merge($counts, $this->calculatePendingCountsForLineOfficialActivityHistory());
        $counts = array_merge($counts, $this->calculatePendingCountsForTotalCount());
        $counts = array_merge($counts, $this->calculatePendingCountsForOpenChatDeleted());

        // ログ出力（1回だけ）
        if (!empty($counts)) {
            addCronLog('【アーカイブ用データベース】インポート予定: ' . implode(', ', $counts));
        } else {
            addCronLog('【アーカイブ用データベース】インポート対象のデータはありません');
        }
    }

    /**
     * openchat_master の差分件数を計算
     *
     * @return array ログ出力用の配列
     */
    private function calculatePendingCountsForOpenChatMaster(): array
    {
        $stmt = $this->targetPdo->query("SELECT MAX(last_updated_at) as max_updated FROM openchat_master");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastUpdated = $result['max_updated'] ?? '1970-01-01 00:00:00';
        $stmt = $this->sourcePdo->prepare("SELECT COUNT(*) FROM open_chat WHERE updated_at >= ?");
        $stmt->execute([$lastUpdated]);
        $count = (int)$stmt->fetchColumn();
        $this->pendingCounts['openchat_master'] = ['last_updated' => $lastUpdated, 'count' => $count];

        return $count > 0 ? ["openchat_master: {$count}件"] : [];
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
     */
    protected function importOpenChatMaster(): void
    {
        // 事前計算した差分データを再利用
        $lastUpdated = $this->pendingCounts['openchat_master']['last_updated'];
        $totalCount = $this->pendingCounts['openchat_master']['count'];

        if ($totalCount === 0) {
            // updated_at が更新されていない場合でも、メンバー数の差分をチェック
            $this->syncMemberCountDifferences();

            // レコード数の整合性を検証し、不一致があれば修正
            $this->verifyAndFixRecordCount(
                'open_chat',
                'openchat_master',
                'id',
                'openchat_id',
                fn($row) => $this->transformOpenChatRow($row)
            );
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
                    // SQLiteのUPSERT（INSERT OR REPLACE）でデータを更新
                    $this->sqliteUpsert('openchat_master', $data);
                }
            },
        );

        // updated_at が更新されていないメンバー数の変更も同期
        $this->syncMemberCountDifferences();

        // レコード数の整合性を検証し、不一致があれば修正
        $this->verifyAndFixRecordCount(
            'open_chat',
            'openchat_master',
            'id',
            'openchat_id',
            fn($row) => $this->transformOpenChatRow($row)
        );
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
     * growth_ranking テーブル（3種類）の差分件数を計算
     *
     * @return array ログ出力用の配列
     */
    private function calculatePendingCountsForGrowthRankings(): array
    {
        $counts = [];
        $rankings = [
            'statistics_ranking_hour' => 'growth_ranking_past_hour',
            'statistics_ranking_hour24' => 'growth_ranking_past_24_hours',
            'statistics_ranking_week' => 'growth_ranking_past_week',
        ];
        foreach ($rankings as $sourceTable => $targetTable) {
            $count = (int)$this->sourcePdo->query("SELECT COUNT(*) FROM $sourceTable")->fetchColumn();
            $this->pendingCounts[$targetTable] = $count;
            if ($count > 0) {
                $counts[] = "{$targetTable}: {$count}件";
            }
        }
        return $counts;
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
            // 事前計算した件数を再利用
            $totalCount = $this->pendingCounts[$targetTable];

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
                        $this->sqlImporter->import($this->targetPdo, $targetTable, $data, self::CHUNK_SIZE);
                    }
                },
            );

            // レコード数の整合性を検証し、不一致があれば修正
            $this->verifyAndFixRecordCount(
                $sourceTable,
                $targetTable,
                'id',
                'ranking_position',
                function ($row) {
                    return [
                        'ranking_position' => $row['id'],
                        'openchat_id' => $row['open_chat_id'],
                        'member_increase_count' => $row['diff_member'],
                        'growth_rate_percent' => $row['percent_increase']
                    ];
                },
                $this->sourcePdo,
                $this->targetPdo
            );
        }
    }

    /**
     * daily_member_statistics の差分件数を計算
     *
     * @return array ログ出力用の配列
     */
    private function calculatePendingCountsForDailyMemberStatistics(): array
    {
        $stmt = $this->targetPdo->query("SELECT COALESCE(MAX(record_id), 0) as max_id FROM daily_member_statistics");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxId = (int)$result['max_id'];
        $stmt = $this->sqliteStatisticsPdo->prepare("SELECT count(*) FROM statistics WHERE id > ?");
        $stmt->execute([$maxId]);
        $count = (int)$stmt->fetchColumn();
        $this->pendingCounts['daily_member_statistics'] = ['max_id' => $maxId, 'count' => $count];

        return $count > 0 ? ["daily_member_statistics: {$count}件"] : [];
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
        // 事前計算した差分データを再利用
        $maxId = $this->pendingCounts['daily_member_statistics']['max_id'];
        $count = $this->pendingCounts['daily_member_statistics']['count'];

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
                    $this->sqlImporter->import($this->targetPdo, 'daily_member_statistics', $data, self::CHUNK_SIZE_SQLITE);
                }
            },
            'daily_member_statistics: %d / %d 件処理完了'
        );

        // レコード数の整合性を検証し、不一致があれば修正
        $this->verifyAndFixRecordCount(
            'statistics',
            'daily_member_statistics',
            'id',
            'record_id',
            function ($row) {
                return [
                    'record_id' => $row['id'],
                    'openchat_id' => $row['open_chat_id'],
                    'member_count' => $row['member'],
                    'statistics_date' => $row['date']
                ];
            },
            $this->sqliteStatisticsPdo,
            $this->targetPdo
        );
    }

    /**
     * line_official_ranking_total_count の差分件数を計算
     *
     * @return array ログ出力用の配列
     */
    private function calculatePendingCountsForTotalCount(): array
    {
        $stmt = $this->targetPdo->query("SELECT COALESCE(MAX(record_id), 0) as max_id FROM line_official_ranking_total_count");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxId = (int)$result['max_id'];
        $stmt = $this->sqliteRankingPositionPdo->prepare("SELECT count(*) FROM total_count WHERE id > ?");
        $stmt->execute([$maxId]);
        $count = (int)$stmt->fetchColumn();
        $this->pendingCounts['line_official_ranking_total_count'] = ['max_id' => $maxId, 'count' => $count];

        return $count > 0 ? ["line_official_ranking_total_count: {$count}件"] : [];
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
        // 事前計算した差分データを再利用
        $maxId = $this->pendingCounts['line_official_ranking_total_count']['max_id'];
        $count = $this->pendingCounts['line_official_ranking_total_count']['count'];

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
                    $this->sqlImporter->import($this->targetPdo, 'line_official_ranking_total_count', $data, self::CHUNK_SIZE_SQLITE);
                }
            },
            'line_official_ranking_total_count: %d / %d 件処理完了'
        );

        // レコード数の整合性を検証し、不一致があれば修正
        $this->verifyAndFixRecordCount(
            'total_count',
            'line_official_ranking_total_count',
            'id',
            'record_id',
            function ($row) {
                return [
                    'record_id' => $row['id'],
                    'activity_trending_total_count' => $row['total_count_rising'],
                    'activity_ranking_total_count' => $row['total_count_ranking'],
                    'recorded_at' => $row['time'],
                    'category_id' => $row['category']
                ];
            },
            $this->sqliteRankingPositionPdo,
            $this->targetPdo
        );
    }

    /**
     * line_official_activity_history（ランキング・急上昇の2テーブル）の差分件数を計算
     *
     * @return array ログ出力用の配列
     */
    private function calculatePendingCountsForLineOfficialActivityHistory(): array
    {
        $counts = [];
        $tables = [
            'ranking' => 'line_official_activity_ranking_history',
            'rising' => 'line_official_activity_trending_history',
        ];
        foreach ($tables as $sourceTable => $targetTable) {
            $stmt = $this->targetPdo->query("SELECT COALESCE(MAX(record_id), 0) as max_id FROM $targetTable");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $maxId = (int)$result['max_id'];
            $stmt = $this->sqliteRankingPositionPdo->prepare("SELECT count(*) FROM {$sourceTable} WHERE id > ?");
            $stmt->execute([$maxId]);
            $count = (int)$stmt->fetchColumn();
            $this->pendingCounts[$targetTable] = ['max_id' => $maxId, 'count' => $count];
            if ($count > 0) {
                $counts[] = "{$targetTable}: {$count}件";
            }
        }
        return $counts;
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
            // 事前計算した差分データを再利用
            $maxId = $this->pendingCounts[$targetTable]['max_id'];
            $count = $this->pendingCounts[$targetTable]['count'];

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
                        $this->sqlImporter->import($this->targetPdo, $targetTable, $data, self::CHUNK_SIZE_SQLITE);
                    }
                },
                "$targetTable: %d / %d 件処理完了"
            );

            // レコード数の整合性を検証し、不一致があれば修正
            $this->verifyAndFixRecordCount(
                $sourceTable,
                $targetTable,
                'id',
                'record_id',
                function ($row) use ($sourceTable) {
                    $positionColumn = $sourceTable === 'ranking' ? 'activity_ranking_position' : 'activity_trending_position';
                    return [
                        'record_id' => $row['id'],
                        'openchat_id' => $row['open_chat_id'],
                        'category_id' => $row['category'],
                        $positionColumn => $row['position'],
                        'recorded_at' => date('Y-m-d H:i:s', strtotime($row['time'])),
                        'record_date' => date('Y-m-d', strtotime($row['date']))
                    ];
                },
                $this->sqliteRankingPositionPdo,
                $this->targetPdo
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
     * ターゲットDBのレコードを一括更新（CASE文で高速化）
     *
     * SQLiteのパラメータ数制限（デフォルト999）を考慮してチャンク処理を行います。
     * 1レコードあたり3パラメータ（id × 2 + member × 1）を使用するため、
     * 999 / 3 = 333が理論上の最大値。安全マージンを考慮して250件ずつ処理します。
     */
    private function bulkUpdateTargetRecordsSqlite(array $records): void
    {
        if (empty($records)) {
            return;
        }

        // SQLiteのパラメータ数制限を考慮したチャンクサイズ
        $recordsPerBatch = 100;

        // レコードをチャンク単位で処理
        foreach (array_chunk($records, $recordsPerBatch) as $chunk) {
            $this->executeBulkUpdateBatch($chunk);
        }
    }

    /**
     * バッチ単位でのUPDATE実行
     */
    private function executeBulkUpdateBatch(array $records): void
    {
        // CASE文を使った一括UPDATE
        $whenClauses = [];
        $openchatIds = [];
        $params = [];

        foreach ($records as $record) {
            $whenClauses[] = "WHEN ? THEN ?";
            $openchatIds[] = $record['id'];
            $params[] = $record['id'];
            $params[] = $record['member'];
        }

        // 全openchat_idを最後に追加
        $params = array_merge($params, $openchatIds);

        $whenClause = implode(' ', $whenClauses);
        $placeholders = implode(',', array_fill(0, count($openchatIds), '?'));

        $sql = "UPDATE openchat_master SET current_member_count = CASE openchat_id {$whenClause} END WHERE openchat_id IN ($placeholders)";

        $stmt = $this->targetPdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * SQLite用UPSERTヘルパー（INSERT ... ON CONFLICT ... DO UPDATE）
     *
     * MySQLの ON DUPLICATE KEY UPDATE と同等の動作をSQLiteで実現します。
     * INSERT OR REPLACE と異なり、既存レコードを削除せずに更新します。
     *
     * 高速化のため一括UPSERT（複数VALUES句）を使用
     * SQLiteのパラメータ数制限（デフォルト999）を考慮してチャンク処理を行います
     */
    protected function sqliteUpsert(string $tableName, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $columns = array_keys($data[0]);
        $columnCount = count($columns);

        // SQLiteのパラメータ数制限（デフォルト999）を考慮したチャンクサイズ
        // openchat_master は13カラムなので、999 / 13 = 76が理論上の最大値
        // 安全マージンを考慮して50件ずつ処理（50 × 13 = 650パラメータ）
        $recordsPerBatch = (int)floor(999 / $columnCount) - 20;

        // データをチャンク単位で処理
        foreach (array_chunk($data, $recordsPerBatch) as $chunk) {
            $this->executeSqliteUpsertBatch($tableName, $chunk, $columns, $columnCount);
        }
    }

    /**
     * SQLite UPSERT のバッチ実行
     */
    private function executeSqliteUpsertBatch(string $tableName, array $chunk, array $columns, int $columnCount): void
    {
        // openchat_master テーブルの主キーは openchat_id
        $primaryKey = 'openchat_id';

        // line_internal_idが重複する既存レコードを削除（openchat_idが異なる場合）
        if ($tableName === 'openchat_master') {
            $this->removeConflictingLineInternalIds($chunk);
        }

        // UPDATE句を生成（主キー以外のカラムを更新）
        $updateClauses = [];
        foreach ($columns as $column) {
            if ($column !== $primaryKey) {
                $updateClauses[] = "{$column} = excluded.{$column}";
            }
        }

        // 一括UPSERT用のVALUES句を生成
        $placeholders = '(' . implode(', ', array_fill(0, $columnCount, '?')) . ')';
        $valuesClause = implode(', ', array_fill(0, count($chunk), $placeholders));

        $sql = "INSERT INTO {$tableName} (" . implode(', ', $columns) . ") " .
            "VALUES {$valuesClause} " .
            "ON CONFLICT({$primaryKey}) DO UPDATE SET " . implode(', ', $updateClauses);

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
     * line_internal_idが重複する既存レコードを削除
     * （openchat_idが異なる場合のみ）
     *
     * openchat_masterテーブルでは line_internal_id にUNIQUE制約があるため、
     * 異なるopenchat_idで同じline_internal_idを持つレコードが存在すると、
     * UPSERT時にUNIQUE制約違反が発生する。
     * これを防ぐため、挿入前に重複する古いレコードを削除する。
     */
    private function removeConflictingLineInternalIds(array $chunk): void
    {
        foreach ($chunk as $row) {
            if (!empty($row['line_internal_id'])) {
                $stmt = $this->targetPdo->prepare(
                    "DELETE FROM openchat_master
                     WHERE line_internal_id = ? AND openchat_id != ?"
                );
                $stmt->execute([$row['line_internal_id'], $row['openchat_id']]);
            }
        }
    }

    /**
     * open_chat_deleted の差分件数を計算
     *
     * @return array ログ出力用の配列
     */
    private function calculatePendingCountsForOpenChatDeleted(): array
    {
        $stmt = $this->targetPdo->query("SELECT MAX(deleted_at) as max_deleted FROM open_chat_deleted");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxDeleted = $result['max_deleted'] ?? '1970-01-01 00:00:00';
        $stmt = $this->sourcePdo->prepare("SELECT COUNT(*) FROM open_chat_deleted WHERE deleted_at > ?");
        $stmt->execute([$maxDeleted]);
        $count = (int)$stmt->fetchColumn();
        $this->pendingCounts['open_chat_deleted'] = ['max_deleted' => $maxDeleted, 'count' => $count];

        return $count > 0 ? ["open_chat_deleted: {$count}件"] : [];
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
        // 事前計算した差分データを再利用
        $maxDeleted = $this->pendingCounts['open_chat_deleted']['max_deleted'];
        $totalCount = $this->pendingCounts['open_chat_deleted']['count'];

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
                    $this->sqlImporter->import($this->targetPdo, 'open_chat_deleted', $data, self::CHUNK_SIZE);
                }
            },
            'open_chat_deleted: %d / %d 件処理完了'
        );

        // レコード数の整合性を検証し、不一致があれば修正
        $this->verifyAndFixRecordCount(
            'open_chat_deleted',
            'open_chat_deleted',
            'id',
            'id',
            null,
            $this->sourcePdo,
            $this->targetPdo
        );
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
     * @param PDO $sourcePdo ソースDB接続 (デフォルトは $this->sourcePdo)
     * @param PDO $targetPdo ターゲットDB接続 (デフォルトは $this->targetPdo)
     * @param string|null $sourceWhereClause ソースのWHERE条件 (オプション)
     */
    private function verifyAndFixRecordCount(
        string $sourceTable,
        string $targetTable,
        string $sourceIdColumn,
        string $targetIdColumn,
        ?callable $transformCallback = null,
        ?PDO $sourcePdo = null,
        ?PDO $targetPdo = null,
        ?string $sourceWhereClause = null
    ): void {
        $sourcePdo = $sourcePdo ?? $this->sourcePdo;
        $targetPdo = $targetPdo ?? $this->targetPdo;

        // ソースとターゲットの全IDを100件ずつ取得して差分を計算
        $missingIds = $this->findMissingIds($sourcePdo, $targetPdo, $sourceTable, $targetTable, $sourceIdColumn, $targetIdColumn, $sourceWhereClause);

        if (empty($missingIds)) {
            // 差分なし（全てのソースレコードがターゲットに存在する）
            return;
        }

        // レコード数を取得（ログ用）
        $sourceCountQuery = "SELECT COUNT(*) FROM {$sourceTable}" . ($sourceWhereClause ? " WHERE {$sourceWhereClause}" : "");
        $sourceCount = $sourcePdo->query($sourceCountQuery)->fetchColumn();
        $targetCount = $targetPdo->query("SELECT COUNT(*) FROM {$targetTable}")->fetchColumn();

        AdminTool::sendDiscordNotify(sprintf(
            '【%s】不足レコード検出: %d 件のソースレコードがターゲットに存在しません (ソース: %d, ターゲット: %d)',
            $targetTable,
            count($missingIds),
            $sourceCount,
            $targetCount
        ));

        // 不足しているレコードを100件ずつチャンクで取得・挿入
        $this->insertMissingRecords(
            $sourcePdo,
            $targetPdo,
            $sourceTable,
            $targetTable,
            $sourceIdColumn,
            $missingIds,
            $transformCallback
        );

        AdminTool::sendDiscordNotify(sprintf('【%s】不足レコード挿入完了: %d 件', $targetTable, count($missingIds)));
    }

    /**
     * ソースとターゲット間で不足しているIDを検出
     *
     * @return array 不足しているIDの配列
     */
    private function findMissingIds(
        PDO $sourcePdo,
        PDO $targetPdo,
        string $sourceTable,
        string $targetTable,
        string $sourceIdColumn,
        string $targetIdColumn,
        ?string $sourceWhereClause
    ): array {
        // ターゲットの全IDを100件ずつ取得
        $targetIds = $this->fetchAllIdsInChunks($targetPdo, $targetTable, $targetIdColumn);

        // ソースの全IDを100件ずつ取得
        $sourceIds = $this->fetchAllIdsInChunks($sourcePdo, $sourceTable, $sourceIdColumn, $sourceWhereClause);

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
        string $idColumn,
        ?string $whereClause = null
    ): array {
        $allIds = [];
        $chunkSize = 100;
        $offset = 0;

        // 全体のレコード数を取得
        $countQuery = "SELECT COUNT(*) FROM {$tableName}" . ($whereClause ? " WHERE {$whereClause}" : "");
        $totalCount = $pdo->query($countQuery)->fetchColumn();

        if ($totalCount === 0) {
            return [];
        }

        // 100件ずつ取得
        while ($offset < $totalCount) {
            $query = "SELECT {$idColumn} FROM {$tableName}" .
                ($whereClause ? " WHERE {$whereClause}" : "") .
                " ORDER BY {$idColumn} LIMIT {$chunkSize} OFFSET {$offset}";

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
        PDO $sourcePdo,
        PDO $targetPdo,
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
            $stmt = $sourcePdo->prepare($query);
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
            if ($targetTable === 'openchat_master') {
                // openchat_masterはUPSERTを使用
                $this->sqliteUpsert($targetTable, $records);
            } else {
                // その他のテーブルはINSERT OR IGNOREを使用
                $this->sqlImporter->import($targetPdo, $targetTable, $records, $chunkSize);
            }
        }
    }
}

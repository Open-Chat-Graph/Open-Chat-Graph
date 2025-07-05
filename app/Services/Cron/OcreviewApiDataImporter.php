<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\Config\AppConfig;
use App\Models\Importer\SqlInsert;
use App\Models\Importer\SqlInsertUpdateWithBindValue;
use App\Models\SQLite\SQLiteStatistics;
use App\Models\SQLite\SQLiteRankingPosition;
use App\Services\Admin\AdminTool;
use PDO;
use PDOStatement;
use Shared\MimimalCmsConfig;

class OcreviewApiDataImporter
{
    private PDO $targetPdo;
    private PDO $sourcePdo;
    private PDO $sqliteStatisticsPdo;
    private PDO $sqliteRankingPositionPdo;

    // Discord notification counter
    private int $discordNotificationCount = 0;

    // Target database configuration
    private const TARGET_DB_NAME = 'ocgraph_sqlapi';

    // Discord notification configuration
    private const DISCORD_NOTIFY_INTERVAL = 100;

    // Chunk size for bulk operations
    private const CHUNK_SIZE = 2000;
    private const CHUNK_SIZE_SQLITE = 10000;

    public function __construct(
        private SqlInsertUpdateWithBindValue $sqlImportUpdater,
        private SqlInsert $sqlImporter,
    ) {}

    /**
     * Execute all import operations
     */
    public function execute(): void
    {
        $this->initializeConnections();

        // Import OpenChat master data
        $this->importOpenChatMaster();

        // Import growth rankings (full refresh)
        $this->importGrowthRankings();

        // Import daily member statistics (incremental)
        $this->importDailyMemberStatistics();

        // Import LINE official activity history
        $this->importLineOfficialActivityHistory();

        $this->importTotalCount();
    }

    /**
     * Initialize database connections
     */
    private function initializeConnections(): void
    {
        // Connect to source database (ocgraph_ocreview)
        $this->sourcePdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', MimimalCmsConfig::$dbHost, 'ocgraph_ocreview'),
            MimimalCmsConfig::$dbUserName,
            MimimalCmsConfig::$dbPassword,
            [
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION TRANSACTION READ ONLY"
            ]
        );

        // Connect to target database (ocreview_api)
        $this->targetPdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', MimimalCmsConfig::$dbHost, self::TARGET_DB_NAME),
            MimimalCmsConfig::$dbUserName,
            MimimalCmsConfig::$dbPassword,
        );

        $this->sqliteStatisticsPdo = SQLiteStatistics::connect([
            'mode' => '?mode=ro'
        ]);

        $this->sqliteRankingPositionPdo = SQLiteRankingPosition::connect([
            'mode' => '?mode=ro'
        ]);
    }

    /**
     * Import/Update openchat_master table
     */
    private function importOpenChatMaster(): void
    {
        // Get the last update timestamp from target
        $stmt = $this->targetPdo->query("SELECT MAX(last_updated_at) as max_updated FROM openchat_master");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastUpdated = $result['max_updated'] ?? '1970-01-01 00:00:00';

        // Get total count
        $countQuery = "SELECT COUNT(*) FROM open_chat WHERE updated_at >= ?";
        $countStmt = $this->sourcePdo->prepare($countQuery);
        $countStmt->execute([$lastUpdated]);
        $totalCount = $countStmt->fetchColumn();

        if ($totalCount === 0) {
            // No records with updated_at >= last_updated_at, check for member count differences
            $this->syncMemberCountDifferences();
            return;
        }

        // Select only updated records from source
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
                    $this->sqlImportUpdater->import($this->targetPdo, 'openchat_master', $data);
                }
            },
        );

        // After processing regular updates, check for member count differences
        $this->syncMemberCountDifferences();
    }

    /**
     * Transform open_chat row to openchat_master format
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
     * Convert emblem value to verification badge
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
     * Convert join method type to text
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
     * Convert Unix timestamp to datetime
     */
    private function convertUnixTimeToDatetime(?int $unixTime): ?string
    {
        if ($unixTime === null || $unixTime === 0) {
            return null;
        }
        return date('Y-m-d H:i:s', $unixTime);
    }

    /**
     * Process data in chunks with a prepared statement
     * 
     * @param PDOStatement $stmt The prepared statement to execute
     * @param array $bindParams Array of parameters to bind [position => [value, type]]
     * @param int $totalCount Total number of records to process
     * @param int $chunkSize Size of each chunk
     * @param callable $processCallback Callback to process fetched data
     * @param string|null $progressMessage Optional progress message format (use %d for counts)
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
            // Bind static parameters
            foreach ($bindParams as $position => [$value, $type]) {
                $stmt->bindValue($position, $value, $type);
            }

            // Bind dynamic parameters (limit and offset)
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
     * Log message only in development mode
     * 
     * @param string $message The message to log
     */
    private function log(string $message): void
    {
        if (AppConfig::$isDevlopment) {
            echo $message . "\n";
        } else {
            $this->discordNotificationCount++;

            // Send notification on first call or every 100th call
            if ($this->discordNotificationCount % self::DISCORD_NOTIFY_INTERVAL === 0) {
                AdminTool::sendDiscordNotify($message);
            }
        }
    }

    /**
     * Import growth rankings (hourly, daily, weekly)
     */
    private function importGrowthRankings(): void
    {
        $rankings = [
            'statistics_ranking_hour' => 'growth_ranking_past_hour',
            'statistics_ranking_hour24' => 'growth_ranking_past_24_hours',
            'statistics_ranking_week' => 'growth_ranking_past_week',
        ];

        foreach ($rankings as $sourceTable => $targetTable) {
            // Get total count
            $countQuery = "SELECT COUNT(*) FROM $sourceTable";
            $totalCount = $this->sourcePdo->query($countQuery)->fetchColumn();

            if ($totalCount === 0) {
                continue;
            }

            // Truncate target table
            $this->targetPdo->exec("TRUNCATE TABLE $targetTable");

            // Select all data from source
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
        }
    }

    /**
     * Import daily member statistics (incremental)
     */
    private function importDailyMemberStatistics(): void
    {
        // Get the maximum record_id from target
        $stmt = $this->targetPdo->query("SELECT COALESCE(MAX(record_id), 0) as max_id FROM daily_member_statistics");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxId = (int)$result['max_id'];

        $stmt = $this->sqliteStatisticsPdo->prepare("SELECT count(*) FROM statistics WHERE id > ?");
        $stmt->execute([$maxId]);
        $count = $stmt->fetchColumn();

        if ($count === 0) {
            return;
        }

        // Query for records after maxId
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
            'Processed %d / %d records for daily_member_statistics'
        );
    }

    private function importTotalCount(): void
    {
        // Get the maximum record_id from target
        $stmt = $this->targetPdo->query("SELECT COALESCE(MAX(record_id), 0) as max_id FROM line_official_ranking_total_count");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxId = (int)$result['max_id'];

        $stmt = $this->sqliteRankingPositionPdo->prepare("SELECT count(*) FROM total_count WHERE id > ?");
        $stmt->execute([$maxId]);
        $count = $stmt->fetchColumn();

        if ($count === 0) {
            return;
        }

        // Query for records after maxId
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
            'Processed %d / %d records for line_official_ranking_total_count'
        );
    }

    /**
     * Import LINE official activity history
     */
    private function importLineOfficialActivityHistory(): void
    {
        $tables = [
            'ranking' => 'line_official_activity_ranking_history',
            'rising' => 'line_official_activity_trending_history',
        ];

        foreach ($tables as $sourceTable => $targetTable) {
            // Get the maximum ID from target to do incremental import
            $stmt = $this->targetPdo->query("SELECT COALESCE(MAX(record_id), 0) as max_id FROM $targetTable");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $maxId = (int)$result['max_id'];

            $stmt = $this->sqliteRankingPositionPdo->prepare("SELECT count(*) FROM {$sourceTable} WHERE id > ?");
            $stmt->execute([$maxId]);
            $count = $stmt->fetchColumn();

            if ($count === 0) {
                continue;
            }

            $positionColumn = $sourceTable === 'ranking' ? 'activity_ranking_position' : 'activity_trending_position';

            // Query for records after maxId
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
                "Processed %d / %d records for $targetTable"
            );
        }
    }

    /**
     * Sync member count differences between source and target
     * This handles cases where only the member count changed but updated_at wasn't updated
     */
    private function syncMemberCountDifferences(): void
    {
        // Get all target records for comparison
        $targetData = $this->getAllTargetRecords();
        
        if (empty($targetData)) {
            return;
        }

        // Convert to lookup array for efficient comparison
        $targetLookup = [];
        foreach ($targetData as $record) {
            $targetLookup[$record['openchat_id']] = $record['current_member_count'];
        }

        // Get total count of source records
        $totalCount = $this->sourcePdo->query("SELECT COUNT(*) FROM open_chat")->fetchColumn();

        if ($totalCount === 0) {
            return;
        }

        // Process source records in chunks to find differences
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
                    
                    // Check if this record exists in target and has member count differences
                    if (isset($targetLookup[$openchatId])) {
                        $targetMemberCount = $targetLookup[$openchatId];
                        
                        // Check if member count is different
                        if ($row['member'] !== $targetMemberCount) {
                            $updatesNeeded[] = $row;
                        }
                    }
                }

                if (!empty($updatesNeeded)) {
                    $this->bulkUpdateTargetRecords($updatesNeeded);
                }
            },
        );
    }

    /**
     * Get all target records for comparison
     */
    private function getAllTargetRecords(): array
    {
        $query = "SELECT openchat_id, current_member_count FROM openchat_master";
        $stmt = $this->targetPdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bulk update target records
     */
    private function bulkUpdateTargetRecords(array $records): void
    {
        if (empty($records)) {
            return;
        }

        // Build bulk update query
        $memberCases = [];
        $ids = [];
        
        foreach ($records as $record) {
            $id = $record['id'];
            $member = $record['member'];
            
            $memberCases[] = "WHEN {$id} THEN {$member}";
            $ids[] = $id;
        }
        
        $idsStr = implode(',', $ids);
        $memberCasesStr = implode(' ', $memberCases);
        
        $query = "
            UPDATE openchat_master 
            SET current_member_count = CASE openchat_id {$memberCasesStr} END
            WHERE openchat_id IN ({$idsStr})
        ";
        
        $this->targetPdo->exec($query);
    }
}

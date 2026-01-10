<?php

declare(strict_types=1);

namespace App\Models\SQLite;

use App\Config\AppConfig;
use Shadow\DBInterface;

/**
 * SQLite connection class for ocgraph_sqlapi database
 * This database stores API data for external access (Japanese only, not multi-language)
 */
class SQLiteOcgraphSqlapi extends AbstractSQLite implements DBInterface
{
    public static ?\PDO $pdo = null;

    /**
     * Connect to ocgraph_sqlapi SQLite database
     *
     * Note: This database is Japanese-only and does not use multi-language paths.
     * It connects to a fixed path instead of using AppConfig::getStorageFilePath().
     *
     * Optimizations applied:
     * - WAL (Write-Ahead Logging) mode for better concurrent read/write performance
     * - NORMAL synchronous mode for balanced performance and durability
     * - 10-second busy timeout to handle concurrent access
     *
     * @param ?array $config array{mode?: ?string} $config mode default is '?mode=rwc'
     * @return \PDO
     */
    public static function connect(?array $config = null): \PDO
    {
        if (static::$pdo !== null) {
            return static::$pdo;
        }

        $mode = $config['mode'] ?? '?mode=rwc';
        static::$pdo = new \PDO('sqlite:file:' . AppConfig::SQLITE_OCGRAPH_SQLAPI_DB_PATH . $mode);

        // Apply PRAGMA settings only for read-write mode
        // Read-only mode (mode=ro) cannot execute PRAGMA statements
        if (!str_contains($mode, 'mode=ro')) {
            // Apply SQLite optimizations (inherited from AbstractSQLite pattern)
            // Enable WAL mode for concurrent read/write performance
            static::$pdo->exec('PRAGMA journal_mode=WAL');

            // Set synchronous mode to NORMAL for balanced performance
            static::$pdo->exec('PRAGMA synchronous=NORMAL');

            // Set busy timeout to 10 seconds to handle concurrent access
            static::$pdo->exec('PRAGMA busy_timeout=10000');
        }

        return static::$pdo;
    }
}

<?php

declare(strict_types=1);

namespace App\Models\SQLite;

use App\Config\AppConfig;
use App\Services\Storage\FileStorageInterface;
use Shadow\DBInterface;
use Shadow\DB;

abstract class AbstractSQLite extends DB implements DBInterface
{
    public static ?\PDO $pdo = null;

    private const MAX_RETRIES = 5;
    private const RETRY_WAIT_MICROSECONDS = 100000; // 0.1 seconds
    private const RETRYABLE_ERRORS = [
        'database disk image is malformed',
        'database is locked',
        '8 attempt to write a readonly database',
        'locking protocol',
    ];

    /**
     * @param ?array{storageFileKey: string, mode?: ?string} $config mode default is '?mode=rwc'
     */
    public static function connect(?array $config = null): \PDO
    {
        if (static::$pdo !== null) {
            return static::$pdo;
        }

        if (empty($config['storageFileKey'])) {
            throw new \InvalidArgumentException('storageFileKey is required');
        }

        $sqliteFilePath = app(FileStorageInterface::class)->getStorageFilePath($config['storageFileKey']);
        $mode = $config['mode'] ?? '?mode=rwc';

        static::$pdo = new \PDO('sqlite:file:' . $sqliteFilePath . $mode);

        // Set busy timeout for all modes (including read-only) to handle concurrent access
        static::$pdo->exec('PRAGMA busy_timeout=10000');

        // Apply write-related PRAGMA settings only for read-write mode
        // Read-only mode (mode=ro) cannot execute journal_mode and synchronous
        if (!str_contains($mode, 'mode=ro')) {
            // Enable WAL mode for concurrent read/write performance
            static::$pdo->exec('PRAGMA journal_mode=WAL');

            // Set synchronous mode to NORMAL for balanced performance
            static::$pdo->exec('PRAGMA synchronous=NORMAL');
        }

        return static::$pdo;
    }

    public static function execute(string $query, ?array $params = null): \PDOStatement
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < self::MAX_RETRIES) {
            try {
                return parent::execute($query, $params);
            } catch (\PDOException $e) {
                $shouldRetry = false;
                foreach (self::RETRYABLE_ERRORS as $error) {
                    if (str_contains($e->getMessage(), $error)) {
                        $shouldRetry = true;
                        break;
                    }
                }

                if (!$shouldRetry) {
                    throw $e;
                }

                $lastException = $e;
                $attempts++;

                if ($attempts < self::MAX_RETRIES) {
                    usleep(self::RETRY_WAIT_MICROSECONDS);
                }
            }
        }

        throw $lastException ?? new \RuntimeException("Failed to execute query after " . self::MAX_RETRIES . " attempts.");
    }
}

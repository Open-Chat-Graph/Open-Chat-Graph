<?php

declare(strict_types=1);

namespace App\Models\SQLite;

use App\Config\FileStorageServiceConfig;
use Shadow\DBInterface;

class SQLiteStatisticsOhlc extends AbstractSQLite implements DBInterface
{
    public static ?\PDO $pdo = null;

    /**
     * @param ?array $config array{mode?: ?string} $config mode default is '?mode=rwc'
     */
    public static function connect(?array $config = null): \PDO
    {
        if (static::$pdo !== null) {
            return static::$pdo;
        }

        $pdo = parent::connect([
            'storageFileKey' => 'sqliteStatisticsOhlcDb',
            'mode' => $config['mode'] ?? null
        ]);

        if (!str_contains(($config['mode'] ?? ''), 'mode=ro')) {
            $schema = file_get_contents(FileStorageServiceConfig::$sqliteSchemaFiles['sqliteStatisticsOhlcDb']);
            $pdo->exec($schema);
        }

        return $pdo;
    }
}

<?php

declare(strict_types=1);

namespace App\Models\SQLite;

use App\Config\AppConfig;
use Shadow\DBInterface;
use App\Models\Repositories\DB;

abstract class AbstractSQLite extends DB implements DBInterface
{
    public static ?\PDO $pdo = null;

    /**
     * @param ?array{storageFileKey: string, mode?: ?string} $config mode default is '?mode=rwc'
     */
    public static function connect(?array $config = null): \PDO
    {
        if (static::$pdo !== null) {
            return static::$pdo;
        }

        if(empty($config['storageFileKey'])) {
            throw new \InvalidArgumentException('storageFileKey is required');
        }

        $spliteFilePath = AppConfig::getStorageFilePath($config['storageFileKey']);
        $mode = $config['mode'] ?? '?mode=rwc';

        static::$pdo = new \PDO('sqlite:file:' . $spliteFilePath . $mode);

        return static::$pdo;
    }
}

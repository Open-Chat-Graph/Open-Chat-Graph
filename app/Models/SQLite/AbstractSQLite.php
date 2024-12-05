<?php

declare(strict_types=1);

namespace App\Models\SQLite;

use Shadow\DBInterface;
use App\Models\Repositories\DB;

abstract class AbstractSQLite extends DB implements DBInterface
{
    public static string $dbfile = __DIR__ . '/../../../storage/SQLite/statistics/statistics.db';
    public static ?\PDO $pdo = null;

    /**
     * @throws \PDOException
     */
    public static function connect(string $mode = '?mode=rwc'): \PDO
    {
        if (static::$pdo !== null) {
            return static::$pdo;
        }

        static::$pdo = new \PDO('sqlite:file:' . static::$dbfile . $mode);

        return static::$pdo;
    }
}

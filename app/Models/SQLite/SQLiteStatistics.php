<?php

declare(strict_types=1);

namespace App\Models\SQLite;

use App\Config\AppConfig;
use Shadow\DBInterface;

class SQLiteStatistics extends AbstractSQLite implements DBInterface
{
    public static string $dbfile = '';
    public static ?\PDO $pdo = null;

    /**
     * @throws \PDOException
     */
    public static function connect(string $mode = '?mode=rwc'): \PDO
    {
        self::$dbfile = AppConfig::$SQLiteStatisticsDbfile;
        return parent::connect($mode);
    }
}

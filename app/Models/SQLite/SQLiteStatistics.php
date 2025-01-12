<?php

declare(strict_types=1);

namespace App\Models\SQLite;

use Shadow\DBInterface;

class SQLiteStatistics extends AbstractSQLite implements DBInterface
{
    public static ?\PDO $pdo = null;

    /**
     * @param ?array $config array{mode?: ?string} $config mode default is '?mode=rwc'
     */
    public static function connect(?array $config = null): \PDO
    {
        return parent::connect([
            'storageFileKey' => 'sqliteStatisticsDb',
            'mode' => $config['mode'] ?? null
        ]);
    }
}

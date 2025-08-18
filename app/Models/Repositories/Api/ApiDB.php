<?php

declare(strict_types=1);

namespace App\Models\Repositories\Api;

use Shadow\DB;

/**
 * Database connection class for ocgraph_sqlapi database
 * This class provides connection to the API database imported by OcreviewApiDataImporter
 */
class ApiDB extends DB
{
    /**
     * Connect to ocgraph_sqlapi database
     * 
     * @param ?array $config Additional configuration (optional)
     * @return \PDO
     */
    public static function connect(?array $config = null): \PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $config = $config ?? [];
        $config['dbName'] = 'ocgraph_sqlapi';

        return parent::connect($config);
    }
}
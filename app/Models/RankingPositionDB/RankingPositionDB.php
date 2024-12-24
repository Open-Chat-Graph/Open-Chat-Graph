<?php

declare(strict_types=1);

namespace App\Models\RankingPositionDB;

use App\Config\AppConfig;
use Shadow\DBInterface;
use Shadow\DB;

class RankingPositionDB extends DB implements DBInterface
{
    public static ?\PDO $pdo = null;

    public static function connect(string $class = ''): \PDO
    {
        return parent::connect($class ?: AppConfig::$RankingPositionDBConfigClass);
    }
}

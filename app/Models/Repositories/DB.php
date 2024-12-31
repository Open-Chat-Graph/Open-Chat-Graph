<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Config\AppConfig;
use Shadow\DBInterface;

class DB extends \Shadow\DB implements DBInterface
{
    public static ?\PDO $pdo = null;

    public static function connect(string $class = ''): \PDO
    {
        return parent::connect($class ?: AppConfig::DB_CONFIG_CLASS[URL_ROOT]);
    }
}

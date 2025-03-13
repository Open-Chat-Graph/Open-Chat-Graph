<?php

declare(strict_types=1);

namespace App\Models\UserLogRepositories;

use App\Config\AppConfig;
use Shadow\DBInterface;
use App\Models\Repositories\DB;
use Shared\MimimalCmsConfig;

class UserLogDB extends DB implements DBInterface
{
    public static ?\PDO $pdo = null;

    public static function connect(?array $config = null): \PDO
    {
        return parent::connect($config ?? [
            'dbName' => AppConfig::$userLogDbName[MimimalCmsConfig::$urlRoot]
        ]);
    }
}

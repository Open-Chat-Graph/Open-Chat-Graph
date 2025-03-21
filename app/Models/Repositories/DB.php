<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Config\AppConfig;
use Shadow\DBInterface;
use Shared\MimimalCmsConfig;

class DB extends \Shadow\DB implements DBInterface
{
    public static ?\PDO $pdo = null;

    public static function connect(?array $config = null): \PDO
    {
        return parent::connect($config ?? [
            'dbName' => AppConfig::$dbName[MimimalCmsConfig::$urlRoot]
        ]);
    }

    public static function execute(string $query, ?array $params = null): \PDOStatement
    {
        try {
            return parent::execute($query, $params);
        } catch (\PDOException $e) {
            if ($e->errorInfo[1] === 2006) {
                static::$pdo = null;
                return parent::execute($query, $params);
            }
            
            throw $e;
        }
    }
}

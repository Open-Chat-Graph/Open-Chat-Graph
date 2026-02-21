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
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return parent::execute($query, $params);
            } catch (\PDOException $e) {
                if ($attempt < 2 && ($e->errorInfo[1] ?? null) === 2006) {
                    static::$pdo = null;
                    sleep(1);
                    continue;
                }

                throw $e;
            }
        }
    }
}

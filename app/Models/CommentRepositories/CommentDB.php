<?php

declare(strict_types=1);

namespace App\Models\CommentRepositories;

use App\Config\AppConfig;
use Shadow\DBInterface;
use App\Models\Repositories\DB;
use Shared\MimimalCmsConfig;

class CommentDB extends DB implements DBInterface
{
    public static ?\PDO $pdo = null;

    public static function connect(?array $config = null): \PDO
    {
        return parent::connect($config ?? [
            'dbName' => AppConfig::$commentDbName[MimimalCmsConfig::$urlRoot]
        ]);
    }
}

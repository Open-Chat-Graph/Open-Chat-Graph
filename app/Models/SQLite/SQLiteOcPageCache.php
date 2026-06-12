<?php

declare(strict_types=1);

namespace App\Models\SQLite;

use Shadow\DBInterface;

class SQLiteOcPageCache extends AbstractSQLite implements DBInterface
{
    public static ?\PDO $pdo = null;

    /**
     * /oc 表示の途中で読まれる「無くても表示できる」DBのため、ロック競合時に
     * リトライ待機の積み上げ（約2.4秒）でページを塞がない。
     * 読み手はさらに busyTimeout を短くして null フォールバックする。
     */
    protected const MAX_RETRIES = 2;

    /**
     * @param ?array $config array{mode?: ?string, busyTimeout?: ?int} $config mode default is '?mode=rwc'
     */
    public static function connect(?array $config = null): \PDO
    {
        return parent::connect([
            'storageFileKey' => 'sqliteOcPageCacheDb',
            'mode' => $config['mode'] ?? null,
            'busyTimeout' => $config['busyTimeout'] ?? null,
        ]);
    }
}

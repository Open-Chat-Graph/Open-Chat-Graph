<?php

declare(strict_types=1);

namespace App\Models\SQLite;

use Shadow\DBInterface;

class SQLiteOcPageCache extends AbstractSQLite implements DBInterface
{
    public static ?\PDO $pdo = null;

    /**
     * /oc 表示の途中で読まれる「無くても表示できる」DBのため、読み取りは2回
     * （busy 20ms×2＋ジッター ≈ 最悪50ms程度）で諦めて null フォールバックする。
     * 書き込み（背景バッチ）は既定のリトライのまま。
     */
    protected const READER_MAX_RETRIES = 2;

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

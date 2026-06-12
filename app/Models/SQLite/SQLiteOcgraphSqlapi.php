<?php

declare(strict_types=1);

namespace App\Models\SQLite;

use App\Config\AppConfig;
use Shadow\DBInterface;

/**
 * SQLite connection class for ocgraph_sqlapi database
 * This database stores API data for external access (Japanese only, not multi-language)
 */
class SQLiteOcgraphSqlapi extends AbstractSQLite implements DBInterface
{
    public static ?\PDO $pdo = null;

    /**
     * Connect to ocgraph_sqlapi SQLite database
     *
     * Note: This database is Japanese-only and does not use multi-language paths.
     * It connects to a fixed path instead of using FileStorageService::getStorageFilePath().
     *
     * 接続の実体は AbstractSQLite::connect に委譲する（busy_timeout・モード追跡付きの
     * 接続使い回し・WAL等のPRAGMA適用条件を全SQLiteで統一するため。以前の独自実装は
     * mode=ro だと busy_timeout が未設定・最初に開いたモードを使い回す問題があった）。
     *
     * @param ?array $config array{mode?: ?string, busyTimeout?: ?int} $config mode default is '?mode=rwc'
     * @return \PDO
     */
    public static function connect(?array $config = null): \PDO
    {
        return parent::connect([
            'filePath' => AppConfig::SQLITE_OCGRAPH_SQLAPI_DB_PATH,
            'mode' => $config['mode'] ?? null,
            'busyTimeout' => $config['busyTimeout'] ?? null,
        ]);
    }
}

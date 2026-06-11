<?php

declare(strict_types=1);

namespace App\Models\SQLite;

use App\Services\Storage\FileStorageInterface;
use Shadow\DBInterface;
use Shadow\DB;

abstract class AbstractSQLite extends DB implements DBInterface
{
    public static ?\PDO $pdo = null;

    /**
     * リトライ設計（クローラー殺到時の WAL 読み取り競合対策）:
     *
     * 「locking protocol」(SQLITE_PROTOCOL) は busy_timeout が効かない。WAL の読み取りマーク
     * スロットは最大5個しかなく、読み側がクエリごとに接続を開き直す本アプリの構造上、
     * アクセス集中時はスロット争奪のリトライ上限超過でこのエラーが出る。
     * 一過性の輻輳なので、指数バックオフ＋ジッターで待てばほぼ確実に抜けられる。
     *
     * - 固定間隔だと全 php-fpm ワーカーが同周期で再衝突する（リトライハード）ため、
     *   ±50% のジッターで分散させる
     * - 最大待機合計は約2.4秒（+ジッター）に制限。殺到時にワーカーを長時間塞ぐと
     *   fpm プール枯渇で別の障害になるため、無制限には待たない
     */
    private const MAX_RETRIES = 7;
    private const RETRY_BASE_WAIT_MICROSECONDS = 50000;   // 初回 0.05 秒
    private const RETRY_MAX_WAIT_MICROSECONDS = 800000;   // 1回あたり上限 0.8 秒
    private const RETRYABLE_ERRORS = [
        'database disk image is malformed',
        'database is locked',
        '8 attempt to write a readonly database',
        'locking protocol',
    ];

    /**
     * @param ?array{storageFileKey: string, mode?: ?string} $config mode default is '?mode=rwc'
     */
    public static function connect(?array $config = null): \PDO
    {
        if (static::$pdo !== null) {
            return static::$pdo;
        }

        if (empty($config['storageFileKey'])) {
            throw new \InvalidArgumentException('storageFileKey is required');
        }

        $sqliteFilePath = app(FileStorageInterface::class)->getStorageFilePath($config['storageFileKey']);
        $mode = $config['mode'] ?? '?mode=rwc';

        static::$pdo = new \PDO('sqlite:file:' . $sqliteFilePath . $mode);

        // Set busy timeout for all modes (including read-only) to handle concurrent access
        static::$pdo->exec('PRAGMA busy_timeout=10000');

        // Apply write-related PRAGMA settings only for read-write mode
        // Read-only mode (mode=ro) cannot execute journal_mode and synchronous
        if (!str_contains($mode, 'mode=ro')) {
            // Enable WAL mode for concurrent read/write performance
            static::$pdo->exec('PRAGMA journal_mode=WAL');

            // Set synchronous mode to NORMAL for balanced performance
            static::$pdo->exec('PRAGMA synchronous=NORMAL');
        }

        return static::$pdo;
    }

    public static function execute(string $query, ?array $params = null): \PDOStatement
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < self::MAX_RETRIES) {
            try {
                return parent::execute($query, $params);
            } catch (\PDOException $e) {
                $shouldRetry = false;
                foreach (self::RETRYABLE_ERRORS as $error) {
                    if (str_contains($e->getMessage(), $error)) {
                        $shouldRetry = true;
                        break;
                    }
                }

                if (!$shouldRetry) {
                    throw $e;
                }

                $lastException = $e;
                $attempts++;

                if ($attempts < self::MAX_RETRIES) {
                    $wait = min(
                        self::RETRY_BASE_WAIT_MICROSECONDS * (2 ** ($attempts - 1)),
                        self::RETRY_MAX_WAIT_MICROSECONDS
                    );
                    // ±50% ジッター（同時リトライの再衝突を分散）
                    usleep(random_int((int)($wait / 2), (int)($wait * 1.5)));
                }
            }
        }

        throw $lastException ?? new \RuntimeException("Failed to execute query after " . self::MAX_RETRIES . " attempts.");
    }
}

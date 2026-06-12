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
    protected const MAX_RETRIES = 7;
    private const RETRY_BASE_WAIT_MICROSECONDS = 50000;   // 初回 0.05 秒
    private const RETRY_MAX_WAIT_MICROSECONDS = 800000;   // 1回あたり上限 0.8 秒
    private const RETRYABLE_ERRORS = [
        'database disk image is malformed',
        'database is locked',
        '8 attempt to write a readonly database',
        'locking protocol',
    ];

    /**
     * @param ?array{storageFileKey: string, mode?: ?string, busyTimeout?: ?int} $config
     *  mode default is '?mode=rwc' / busyTimeout default is 10000ms。
     *  busyTimeout はロック競合時に各クエリが待つ上限。ページ表示の途中で読む
     *  「無くても表示できるデータ」は短い値＋呼び出し側の null フォールバックで
     *  ロック待ちがレスポンスを塞がないようにする（PR #367 → #370 差し戻しの教訓:
     *  rw 化だけだと競合時に busy_timeout=10秒 を全員が待ち全ページが激遅化する）。
     */
    /** @var array<class-string, string> 接続中のモード（接続使い回し判定用） */
    private static array $connectedMode = [];

    public static function connect(?array $config = null): \PDO
    {
        $mode = $config['mode'] ?? '?mode=rwc';

        if (static::$pdo !== null) {
            // 同一モードなら使い回す。WALの読み取りスナップショット取得や私的wal-index再構築は
            // 接続のたびに発生するため、クエリごとの開き直しはアクセス集中時の
            // 「locking protocol」の温床になる（リクエスト内では1接続を維持する）。
            // モードが変わるときだけ張り直す（ro読み→rwc書きの取り違え防止。
            // 以前は connect がモード指定を無視したため、各リポジトリが毎回
            // `::$pdo = null` で切断するしかなかった——その明示切断は全廃済み）。
            if ((self::$connectedMode[static::class] ?? null) === $mode) {
                return static::$pdo;
            }
            static::$pdo = null;
        }

        if (empty($config['storageFileKey'])) {
            throw new \InvalidArgumentException('storageFileKey is required');
        }

        $sqliteFilePath = app(FileStorageInterface::class)->getStorageFilePath($config['storageFileKey']);

        static::$pdo = new \PDO('sqlite:file:' . $sqliteFilePath . $mode);
        self::$connectedMode[static::class] = $mode;

        // Set busy timeout for all modes (including read-only) to handle concurrent access
        static::$pdo->exec('PRAGMA busy_timeout=' . (int)($config['busyTimeout'] ?? 10000));

        // WAL/synchronous の設定は書き込み側(rwc/既定)の接続だけが行う。
        // mode=ro は実行不可、mode=rw(読み取り用途) は journal_mode の実行自体が
        // ロックを取り読み書きの競合を増やすため実行しない（WAL化は一度行えば永続する）
        if (str_contains($mode, 'rwc')) {
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

        while ($attempts < static::MAX_RETRIES) {
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

                if ($attempts < static::MAX_RETRIES) {
                    $wait = min(
                        self::RETRY_BASE_WAIT_MICROSECONDS * (2 ** ($attempts - 1)),
                        self::RETRY_MAX_WAIT_MICROSECONDS
                    );
                    // ±50% ジッター（同時リトライの再衝突を分散）
                    usleep(random_int((int)($wait / 2), (int)($wait * 1.5)));
                }
            }
        }

        throw $lastException ?? new \RuntimeException("Failed to execute query after " . static::MAX_RETRIES . " attempts.");
    }
}

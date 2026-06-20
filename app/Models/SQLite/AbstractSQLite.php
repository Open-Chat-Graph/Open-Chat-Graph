<?php

declare(strict_types=1);

namespace App\Models\SQLite;

use App\Exceptions\TransientDatabaseException;
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
    /**
     * Web リクエスト中の読み取り接続の共通プロファイル。
     *
     * - mode=rw: WAL の -shm(wal-index 共有メモリ)を共有し「読みは書きを待たない」を使う。
     *   mode=ro は -shm に触れないため接続のたびに私的 wal-index 再構築＋DBファイルの
     *   排他ロックが走り、バッチの書き込み・checkpoint と衝突して読み取り同士まで
     *   直列化する（2026-06-12 の /oc 間欠20秒ストール障害）
     * - busyTimeout=20ms: 1回のロック待ちを短く刻む。続きはリトライ側のジッター付き
     *   待機で互いにずらして再試行する（下記 READER_RETRY_*）。既定の10秒のまま rw 化
     *   すると競合時に全リクエストが10秒待ちになる（PR #367→#370 差し戻しの事故）
     * - persistent=true: FPM ワーカー内で接続(と -shm/wal-index のマップ・読みマークスロット)を
     *   リクエストをまたいで使い回す。クエリ毎に接続を開き直す churn こそが wal-index ロック競合
     *   （SQLITE_PROTOCOL "locking protocol" / SQLITE_BUSY "database is locked"）の主因のため、
     *   読み取り接続を永続化して churn 自体を断つ。SQLite 3.26 では busy_timeout が wal-index
     *   ロックに効かない（blocking locks は 3.30+）ので「待つ」より「開き直さない」方が効く。
     *   書き込み(rwc)接続は cron 単一プロセスで churn が無く、トランザクション持ち越しリスクも
     *   あるため persistent にしない（読み取り専用の rw/ro 接続にのみ付ける）。
     */
    public const WEB_READER = ['mode' => '?mode=rw', 'busyTimeout' => 20, 'persistent' => true];

    protected const MAX_RETRIES = 7;
    // 書き込み(rwc)接続用: busy_timeout=10秒が主に待つので、間隔はゆっくり長く
    private const RETRY_BASE_WAIT_MICROSECONDS = 50000;   // 初回 0.05 秒
    private const RETRY_MAX_WAIT_MICROSECONDS = 800000;   // 1回あたり上限 0.8 秒
    // 読み取り(ro/rw)接続用: 最初は細かく刻んでジッターで互いにずらし（典型の競合は
    // 数十msで抜ける）、続くなら指数的に粘る。10回合計でも最悪2秒程度で諦める
    protected const READER_MAX_RETRIES = 10;
    private const READER_RETRY_BASE_WAIT_MICROSECONDS = 5000;   // 初回 5ms
    private const READER_RETRY_MAX_WAIT_MICROSECONDS = 300000;  // 1回あたり上限 0.3 秒
    private const RETRYABLE_ERRORS = [
        'database disk image is malformed',
        'database is locked',
        '8 attempt to write a readonly database',
        'locking protocol',
    ];

    /**
     * @param ?array{storageFileKey?: string, filePath?: string, mode?: ?string, busyTimeout?: ?int, persistent?: ?bool} $config
     *  mode default is '?mode=rwc' / busyTimeout default is 10000ms。
     *  Web リクエスト中の読み取りは self::WEB_READER を渡す（rw + 短い busy_timeout + persistent）。
     *  バッチの読み取りは '?mode=rw' のみ指定し busyTimeout は既定の10秒のままにする。
     *  persistent=true で PDO 永続接続にする（FPM ワーカーで接続/-shm を使い回し churn を断つ）。
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

        if (empty($config['storageFileKey']) && empty($config['filePath'])) {
            throw new \InvalidArgumentException('storageFileKey or filePath is required');
        }

        // filePath は多言語パスを持たない固定パスDB（ocgraph_sqlapi 等）用
        $sqliteFilePath = $config['filePath']
            ?? app(FileStorageInterface::class)->getStorageFilePath($config['storageFileKey']);

        // 読み取り(rw/ro)接続のみ persistent にして churn を断つ（WEB_READER 経由）。
        // 書き込み(rwc)はトランザクション持ち越し事故を避けるため非 persistent のまま。
        $options = (!empty($config['persistent']) && !str_contains($mode, 'rwc'))
            ? [\PDO::ATTR_PERSISTENT => true]
            : [];
        static::$pdo = new \PDO('sqlite:file:' . $sqliteFilePath . $mode, null, null, $options);
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
        // 読み取り接続(ro/rw)は busy_timeout が短い前提で、待ちの主体はジッター付きリトライ
        $isReader = !str_contains(self::$connectedMode[static::class] ?? '?mode=rwc', 'rwc');
        $maxRetries = $isReader ? static::READER_MAX_RETRIES : static::MAX_RETRIES;
        $baseWait = $isReader ? self::READER_RETRY_BASE_WAIT_MICROSECONDS : self::RETRY_BASE_WAIT_MICROSECONDS;
        $maxWait = $isReader ? self::READER_RETRY_MAX_WAIT_MICROSECONDS : self::RETRY_MAX_WAIT_MICROSECONDS;

        $attempts = 0;
        $lastException = null;

        while ($attempts < $maxRetries) {
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

                if ($attempts < $maxRetries) {
                    $wait = min($baseWait * (2 ** ($attempts - 1)), $maxWait);
                    // ±50% ジッター（同時リトライの再衝突を分散）
                    usleep(random_int((int)($wait / 2), (int)($wait * 1.5)));
                }
            }
        }

        // リトライを尽くした一過性ロック競合は、接続枯渇(MySQL)と同じドメイン例外に統一する。
        // これにより Web では 503 + 10件バッチ通知、CLI(cron)では即時通知へ上位で出し分けられる。
        // malformed(corrupt)/readonly はほぼ恒久障害なので含めず、生 PDOException のまま即通知させる。
        if ($lastException !== null && static::isTransientLockError($lastException)) {
            throw new TransientDatabaseException('Transient database failure: sqlite lock', 0, $lastException);
        }

        throw $lastException ?? new \RuntimeException("Failed to execute query after {$maxRetries} attempts.");
    }

    /**
     * SQLite の一過性ロック競合（リトライで抜けられる類）かどうか。
     *
     * - database is locked (SQLITE_BUSY): テーブル/トランザクションのロック待ち
     * - locking protocol  (SQLITE_PROTOCOL): WAL の読み取りマークスロット枯渇（churn が主因）
     *
     * malformed(corrupt) や readonly はほぼ恒久障害なので一過性に含めない。
     */
    protected static function isTransientLockError(\PDOException $e): bool
    {
        $message = $e->getMessage();
        return str_contains($message, 'database is locked')
            || str_contains($message, 'locking protocol');
    }
}

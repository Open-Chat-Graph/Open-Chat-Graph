<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Config\AppConfig;
use Shadow\DBInterface;
use Shared\MimimalCmsConfig;

class DB extends \Shadow\DB implements DBInterface
{
    public static ?\PDO $pdo = null;

    /**
     * 接続数上限エラー時のリトライ回数（初回 + リトライ）
     */
    private const CONNECT_MAX_ATTEMPTS = 3;

    public static function connect(?array $config = null): \PDO
    {
        $config ??= ['dbName' => AppConfig::$dbName[MimimalCmsConfig::$urlRoot]];

        for ($attempt = 0; $attempt < self::CONNECT_MAX_ATTEMPTS; $attempt++) {
            try {
                return parent::connect($config);
            } catch (\PDOException $e) {
                // 接続数上限（max_user_connections / too many connections）の瞬間的なスパイクは
                // 少し待ってリトライすれば空きが出て捌ける場合がある。
                // 失敗時は static::$pdo は null のまま（new \PDO が例外を投げたため）なので再試行できる。
                if ($attempt < self::CONNECT_MAX_ATTEMPTS - 1 && static::isTooManyConnections($e)) {
                    // FPMワーカーを長時間占有しないよう待機は短く、軽いジッタで分散させる
                    usleep(random_int(100000, 250000) * ($attempt + 1)); // 約0.1〜0.5秒
                    continue;
                }

                throw $e;
            }
        }

        throw new \LogicException('Unreachable');
    }

    /**
     * 接続数上限エラーかどうかを判定する
     *
     * connect()（PDO::__construct）で発生するため errorInfo が未設定のことが多い。
     * その場合に備えてメッセージでも判定する。
     */
    private static function isTooManyConnections(\PDOException $e): bool
    {
        // 1226: ER_USER_LIMIT_REACHED (max_user_connections)
        // 1040: ER_CON_COUNT_ERROR (Too many connections)
        $driverCode = $e->errorInfo[1] ?? null;
        if ($driverCode !== null) {
            $driverCode = (int) $driverCode;
            if ($driverCode === 1226 || $driverCode === 1040) {
                return true;
            }
        }

        $message = $e->getMessage();
        return str_contains($message, 'max_user_connections')
            || str_contains($message, 'max_connections')
            || str_contains($message, 'Too many connections')
            || str_contains($message, '[1226]')
            || str_contains($message, '[1040]');
    }

    public static function execute(string $query, ?array $params = null): \PDOStatement
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return parent::execute($query, $params);
            } catch (\PDOException $e) {
                if ($attempt < 2 && static::isConnectionLost($e)) {
                    static::reconnect();
                    continue;
                }

                throw $e;
            }
        }

        throw new \LogicException('Unreachable');
    }

    /**
     * MySQL接続断エラーかどうかを判定する
     *
     * PDOExceptionのerrorInfoプロパティがnullの場合や、
     * ドライバーエラーコードが文字列の場合にも対応する。
     */
    private static function isConnectionLost(\PDOException $e): bool
    {
        // errorInfo[1] にドライバー固有のエラーコードがある場合（型を問わず比較）
        $driverCode = $e->errorInfo[1] ?? null;
        if ($driverCode !== null) {
            $driverCode = (int) $driverCode;
            // 2006: MySQL server has gone away
            // 2013: Lost connection to MySQL server during query
            if ($driverCode === 2006 || $driverCode === 2013) {
                return true;
            }
        }

        // errorInfoが未設定の場合のフォールバック: エラーメッセージで判定
        $message = $e->getMessage();
        return str_contains($message, 'server has gone away')
            || str_contains($message, 'Lost connection');
    }

    /**
     * 一時的なMySQL接続障害（サーバの瞬断・再起動・接続数スパイク）かどうかを判定する。
     *
     * 個別クエリ単位の再接続（execute()/connect() の内部リトライ）では救えない、
     * 「サーバ自体が数十秒〜分単位で消える」ケースを判定するための広めの分類器。
     * 毎時処理のような冪等な一括処理を、丸ごと少し待って再試行してよいかの判断に使う。
     *
     * - 2006: server has gone away / 2013: Lost connection（動作中クエリ）
     * - 2002: サーバ未起動・ソケット無し / Connection refused（新規接続が張れない）
     * - 1226/1040: 接続数上限（max_user_connections / too many connections）
     *
     * 例外チェーン（getPrevious）を辿り、別プロセスのDBエラー文字列を包んだ
     * RuntimeException（例: バックグラウンドDB反映失敗の通知）も拾えるよう、
     * 型とメッセージの両面で判定する。
     */
    public static function isConnectionException(\Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof \PDOException) {
                if (static::isConnectionLost($current) || static::isTooManyConnections($current)) {
                    return true;
                }

                // 2002: Can't connect / No such file or directory
                // （PDO::__construct で発生し errorInfo が未設定のことが多い）
                $driverCode = $current->errorInfo[1] ?? null;
                if ($driverCode !== null && (int) $driverCode === 2002) {
                    return true;
                }
            }

            $message = $current->getMessage();
            if (
                str_contains($message, 'server has gone away')
                || str_contains($message, 'Lost connection')
                || str_contains($message, '[2002]')
                || str_contains($message, "Can't connect to")
                || str_contains($message, 'Connection refused')
                || str_contains($message, 'Too many connections')
                || str_contains($message, 'max_user_connections')
                || str_contains($message, 'max_connections')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * MySQL接続をリセットして再接続する
     */
    private static function reconnect(): void
    {
        static::$pdo = null;
        sleep(1);
        static::connect();
    }
}

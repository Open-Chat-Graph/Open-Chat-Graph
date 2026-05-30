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
     * MySQL接続をリセットして再接続する
     */
    private static function reconnect(): void
    {
        static::$pdo = null;
        sleep(1);
        static::connect();
    }
}

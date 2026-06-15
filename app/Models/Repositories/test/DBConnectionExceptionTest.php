<?php

/**
 * DB::isConnectionException() のテスト
 *
 * 実行コマンド:
 * docker compose exec app ./vendor/bin/phpunit app/Models/Repositories/test/DBConnectionExceptionTest.php
 *
 * テスト内容:
 * - 一時的なMySQL接続障害（2006/2013/2002/接続数上限）を true と判定する
 * - errorInfo 未設定でもメッセージから判定できる（PDO::__construct 由来の 2002 等）
 * - 別プロセスのDBエラー文字列を包んだ RuntimeException・例外チェーンも拾う
 * - 接続障害でない例外（SQL構文エラー等）は false と判定する
 *
 * DB接続を一切張らない純粋ロジックのテスト（本番データに触れない）。
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Models\Repositories\DB;

class DBConnectionExceptionTest extends TestCase
{
    private function pdoException(string $message, ?array $errorInfo = null): \PDOException
    {
        $e = new \PDOException($message);
        if ($errorInfo !== null) {
            $e->errorInfo = $errorInfo;
        }
        return $e;
    }

    public function test_server_has_gone_away_2006(): void
    {
        $e = $this->pdoException(
            'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away',
            ['HY000', 2006, 'MySQL server has gone away'],
        );
        $this->assertTrue(DB::isConnectionException($e));
    }

    public function test_lost_connection_2013(): void
    {
        $e = $this->pdoException(
            'SQLSTATE[HY000]: General error: 2013 Lost connection to MySQL server during query',
            ['HY000', 2013, 'Lost connection to MySQL server during query'],
        );
        $this->assertTrue(DB::isConnectionException($e));
    }

    public function test_cannot_connect_2002_message_only(): void
    {
        // PDO::__construct 由来は errorInfo 未設定のことが多い。今朝の本番障害がこの形。
        $e = $this->pdoException('SQLSTATE[HY000] [2002] No such file or directory');
        $this->assertTrue(DB::isConnectionException($e));
    }

    public function test_connection_refused_message_only(): void
    {
        $e = $this->pdoException("SQLSTATE[HY000] [2002] Can't connect to MySQL server on 'localhost' (Connection refused)");
        $this->assertTrue(DB::isConnectionException($e));
    }

    public function test_too_many_connections_1040(): void
    {
        $e = $this->pdoException(
            'SQLSTATE[HY000] [1040] Too many connections',
            ['HY000', 1040, 'Too many connections'],
        );
        $this->assertTrue(DB::isConnectionException($e));
    }

    public function test_max_user_connections_1226(): void
    {
        $e = $this->pdoException(
            "SQLSTATE[42000] [1226] User 'x' has exceeded the 'max_user_connections' resource",
            ['42000', 1226, "User 'x' has exceeded the 'max_user_connections' resource"],
        );
        $this->assertTrue(DB::isConnectionException($e));
    }

    public function test_wrapped_runtime_exception_from_background_process(): void
    {
        // バックグラウンドDB反映プロセスの失敗通知（型は RuntimeException だが本文に接続断を含む）
        $e = new \RuntimeException(
            'バックグラウンドDB反映プロセスがエラーで終了: SQLSTATE[HY000]: General error: 2006 MySQL server has gone away',
        );
        $this->assertTrue(DB::isConnectionException($e));
    }

    public function test_exception_chain_with_previous_pdo(): void
    {
        $previous = $this->pdoException(
            'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away',
            ['HY000', 2006, 'MySQL server has gone away'],
        );
        $e = new \RuntimeException('毎時処理に失敗しました', 0, $previous);
        $this->assertTrue(DB::isConnectionException($e));
    }

    public function test_sql_syntax_error_is_not_connection(): void
    {
        $e = $this->pdoException(
            "SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax",
            ['42000', 1064, 'You have an error in your SQL syntax'],
        );
        $this->assertFalse(DB::isConnectionException($e));
    }

    public function test_generic_exception_is_not_connection(): void
    {
        $this->assertFalse(DB::isConnectionException(new \LogicException('something else')));
    }
}

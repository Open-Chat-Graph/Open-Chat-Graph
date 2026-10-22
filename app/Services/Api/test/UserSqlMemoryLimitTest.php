<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Api/test/UserSqlMemoryLimitTest.php
 */

declare(strict_types=1);

use App\Services\Api\UserSqlMemoryLimit;
use PHPUnit\Framework\TestCase;

class UserSqlMemoryLimitTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    /**
     * 巨大な値を作るクエリは上限で out of memory になる（関数の桁指定・|| の入れ子のどちらも）
     */
    public function testHugeValuesFailWithOutOfMemory(): void
    {
        $nested = "WITH a AS (SELECT 'xxxxxxxxxxxxxxxx' x)";
        $prev = 'a';
        foreach (range('b', 'i') as $name) {
            $nested .= ", {$name} AS (SELECT x||x||x||x||x||x||x||x x FROM {$prev})";
            $prev = $name;
        }
        $nested .= " SELECT length(x) FROM {$prev}";

        foreach (["SELECT length(printf('%600000000s', ''))", $nested] as $sql) {
            try {
                UserSqlMemoryLimit::run(fn() => $this->pdo->query($sql)->fetchAll());
                $this->fail('上限で止まらなかった: ' . $sql);
            } catch (\PDOException $e) {
                $this->assertStringContainsString('out of memory', $e->getMessage());
            }
        }
    }

    /**
     * 普通のクエリは結果が返り、上限は実行中だけ下がって元に戻る
     */
    public function testLimitIsLoweredOnlyWhileRunning(): void
    {
        $before = posix_getrlimit(POSIX_RLIMIT_AS);

        $result = UserSqlMemoryLimit::run(function () {
            $this->assertTrue(UserSqlMemoryLimit::isActive());
            $this->assertSame(1, posix_getrlimit(POSIX_RLIMIT_AS)[0] % 2, '下げた値は目印の奇数');
            return $this->pdo->query("SELECT length(printf('%1000000s', ''))")->fetchColumn();
        });

        $this->assertSame(1000000, $result);
        $this->assertFalse(UserSqlMemoryLimit::isActive());
        $this->assertSame($before, posix_getrlimit(POSIX_RLIMIT_AS));
    }

    /**
     * 例外で抜けても上限は戻る（FastCGI のプロセスは次のリクエストにも使い回される）
     */
    public function testLimitIsRestoredAfterException(): void
    {
        $before = posix_getrlimit(POSIX_RLIMIT_AS);

        try {
            UserSqlMemoryLimit::run(fn() => $this->pdo->query('SELECT * FROM no_such_table'));
        } catch (\PDOException) {
        }

        $this->assertFalse(UserSqlMemoryLimit::isActive());
        $this->assertSame($before, posix_getrlimit(POSIX_RLIMIT_AS));
    }

    /**
     * 致命的エラーで戻せずに残った上限（目印の奇数）は、次のリクエストの最初に戻せる。
     * 目印の無い（偶数の）上限は運用側の設定とみなして触らない
     */
    public function testRestoreLeftoverOnlyTouchesMarkedLimit(): void
    {
        $before = posix_getrlimit(POSIX_RLIMIT_AS);
        $this->assertSame($before[0], $before[1], 'このテストは soft = hard の環境が前提');
        $hard = $before[1] === 'unlimited' ? POSIX_RLIMIT_INFINITY : $before[1];

        try {
            posix_setrlimit(POSIX_RLIMIT_AS, 8 * 1024 * 1024 * 1024, $hard);
            UserSqlMemoryLimit::restoreLeftover();
            $this->assertSame(8 * 1024 * 1024 * 1024, posix_getrlimit(POSIX_RLIMIT_AS)[0]);

            posix_setrlimit(POSIX_RLIMIT_AS, 8 * 1024 * 1024 * 1024 + 1, $hard);
            UserSqlMemoryLimit::restoreLeftover();
            $this->assertSame($before, posix_getrlimit(POSIX_RLIMIT_AS));
        } finally {
            posix_setrlimit(POSIX_RLIMIT_AS, $hard, $hard);
        }
    }
}

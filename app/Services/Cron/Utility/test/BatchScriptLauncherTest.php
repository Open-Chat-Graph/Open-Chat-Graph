<?php

/**
 * BatchScriptLauncher::run() の一過性DB障害リトライのテスト
 *
 * 実行コマンド:
 * docker compose exec app ./vendor/bin/phpunit app/Services/Cron/Utility/test/BatchScriptLauncherTest.php
 *
 * テスト内容:
 * - 成功時は再試行も通知もしない
 * - 一過性DB障害(TransientDatabaseException/接続障害)は全体を再試行し、回復したら通知しない
 * - 再試行を尽くしたら1回だけ通知する（TRANSIENT_MAX_ATTEMPTS=4）
 * - 恒久的な例外(バグ)は再試行せず即通知する
 * - suppressNotify が true を返したら通知しない
 *
 * sleep / Discord / storage には触れない（onTransientRetry・report・flush を差し替え）。
 */

declare(strict_types=1);

use App\Exceptions\TransientDatabaseException;
use App\Services\Cron\Utility\BatchScriptLauncher;
use PHPUnit\Framework\TestCase;

/**
 * 待機・通知・端数flushを差し替えて、純粋にリトライ制御だけを検証するテスト用サブクラス。
 */
class RetryTestableLauncher extends BatchScriptLauncher
{
    public int $retryCount = 0;
    public int $reportCount = 0;

    protected function onTransientRetry(\Throwable $e, int $attempt, int $maxAttempts): void
    {
        // 実際は cronログ＋sleep(60) だが、テストでは回数だけ数える
        $this->retryCount++;
    }

    protected function report(\Throwable $e): void
    {
        $this->reportCount++;
    }

    protected function flushDeferredTransientErrors(): void
    {
        // 実 Discord / storage に触れない
    }
}

class BatchScriptLauncherTest extends TestCase
{
    private function transient(): TransientDatabaseException
    {
        return new TransientDatabaseException(
            'sqlite lock',
            0,
            new \PDOException('SQLSTATE[HY000]: General error: 5 database is locked'),
        );
    }

    public function test_success_no_retry_no_report(): void
    {
        $launcher = new RetryTestableLauncher();
        $calls = 0;

        $launcher->run(function () use (&$calls) {
            $calls++;
        });

        $this->assertSame(1, $calls);
        $this->assertSame(0, $launcher->retryCount);
        $this->assertSame(0, $launcher->reportCount);
    }

    public function test_transient_then_success_retries(): void
    {
        $launcher = new RetryTestableLauncher();
        $calls = 0;

        $launcher->run(function () use (&$calls) {
            $calls++;
            if ($calls < 3) {
                throw $this->transient();
            }
        });

        $this->assertSame(3, $calls, '一過性エラーで2回再試行し3回目で成功');
        $this->assertSame(2, $launcher->retryCount);
        $this->assertSame(0, $launcher->reportCount, '回復したので通知しない');
    }

    public function test_transient_exhausts_then_reports_once(): void
    {
        $launcher = new RetryTestableLauncher();
        $calls = 0;

        $launcher->run(function () use (&$calls) {
            $calls++;
            throw $this->transient();
        });

        $this->assertSame(4, $calls, 'TRANSIENT_MAX_ATTEMPTS=4 回まで試行');
        $this->assertSame(3, $launcher->retryCount);
        $this->assertSame(1, $launcher->reportCount, '尽きたら1回だけ通知');
    }

    public function test_non_transient_no_retry_reports_once(): void
    {
        $launcher = new RetryTestableLauncher();
        $calls = 0;

        $launcher->run(function () use (&$calls) {
            $calls++;
            throw new \LogicException('genuine bug');
        });

        $this->assertSame(1, $calls, '恒久的な不具合は再試行しない');
        $this->assertSame(0, $launcher->retryCount);
        $this->assertSame(1, $launcher->reportCount);
    }

    public function test_suppress_notify_skips_report(): void
    {
        $launcher = new RetryTestableLauncher();

        $launcher->run(
            function () {
                throw new \LogicException('expected interruption');
            },
            fn(\Throwable $e): bool => true,
        );

        $this->assertSame(0, $launcher->reportCount, '抑制コールバックが true なら通知しない');
    }
}

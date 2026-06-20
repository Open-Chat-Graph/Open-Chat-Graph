<?php

/**
 * DeferredTransientErrorNotifier のテスト
 *
 * 実行コマンド:
 * docker compose exec app ./vendor/bin/phpunit app/Services/Error/test/DeferredTransientErrorNotifierTest.php
 *
 * テスト内容:
 * - 9件までは送信せずキューに溜める / 10件目でまとめて1回送信しキューを空にする
 * - flush() で端数(10件未満)を即送信して空にする / 空のときは送信しない
 * - 整形行は元例外($previous の PDOException)のクラス名・メッセージで作られる
 *
 * 実際の Discord 送信は TestableNotifier で差し替え、外部通信せず純粋にロジックを検証する。
 */

declare(strict_types=1);

use App\Exceptions\TransientDatabaseException;
use App\Services\Error\DeferredTransientErrorNotifier;
use PHPUnit\Framework\TestCase;

/**
 * send() を差し替えて送信内容をキャプチャするテスト用サブクラス。
 */
class TestableNotifier extends DeferredTransientErrorNotifier
{
    /** @var string[][] 送信されたメッセージ行の配列(send 1回につき1要素) */
    public array $sent = [];

    protected function send(array $lines): void
    {
        $this->sent[] = $lines;
    }
}

class DeferredTransientErrorNotifierTest extends TestCase
{
    private string $queueFile;
    private TestableNotifier $notifier;

    protected function setUp(): void
    {
        $this->queueFile = tempnam(sys_get_temp_dir(), 'transient_queue_test_');
        // tempnam は空ファイルを作るので消しておく(未作成状態から始める)
        $this->removeIfExists($this->queueFile);
        $this->notifier = new TestableNotifier($this->queueFile);
    }

    protected function tearDown(): void
    {
        $this->removeIfExists($this->queueFile);
        $this->removeIfExists($this->queueFile . '.lock');
    }

    private function removeIfExists(string $path): void
    {
        // 当フレームワークのエラーハンドラは @ 抑制でも警告を例外化するため is_file で守る
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function transientException(string $message = 'SQLSTATE[HY000] [2006] MySQL server has gone away'): TransientDatabaseException
    {
        $pdo = new \PDOException($message);
        return new TransientDatabaseException('Transient database failure', 0, $pdo);
    }

    public function test_under_threshold_does_not_send(): void
    {
        for ($i = 0; $i < 9; $i++) {
            $this->notifier->record($this->transientException());
        }

        $this->assertSame([], $this->notifier->sent, '9件では送信されない');
        $this->assertSame(9, $this->countQueueLines(), '9件がキューに残る');
    }

    public function test_tenth_record_sends_ten_and_clears(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->notifier->record($this->transientException());
        }

        $this->assertCount(1, $this->notifier->sent, '10件目でちょうど1回送信される');
        $this->assertCount(10, $this->notifier->sent[0], '10件をまとめて送る');
        $this->assertSame(0, $this->countQueueLines(), '送信後はキューが空になる');
    }

    public function test_twenty_records_send_twice(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->notifier->record($this->transientException());
        }

        $this->assertCount(2, $this->notifier->sent, '20件で2回送信される');
        $this->assertCount(10, $this->notifier->sent[1]);
        $this->assertSame(0, $this->countQueueLines());
    }

    public function test_flush_sends_remainder(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->notifier->record($this->transientException());
        }
        $this->assertSame([], $this->notifier->sent);

        $this->notifier->flush();

        $this->assertCount(1, $this->notifier->sent, 'flush で端数が送られる');
        $this->assertCount(3, $this->notifier->sent[0]);
        $this->assertSame(0, $this->countQueueLines());
    }

    public function test_flush_on_empty_does_not_send(): void
    {
        $this->notifier->flush();
        $this->assertSame([], $this->notifier->sent, '空なら送信しない');
    }

    public function test_line_uses_original_pdo_class_and_message(): void
    {
        $this->notifier->record($this->transientException('SQLSTATE[HY000]: General error: 5 database is locked'));
        $this->notifier->flush();

        $line = $this->notifier->sent[0][0];
        $this->assertStringContainsString('PDOException:', $line, '元例外(PDOException)のクラス名で記録される');
        $this->assertStringContainsString('database is locked', $line);
        $this->assertStringStartsWith('- ', $line, 'MDリスト形式の行');
    }

    private function countQueueLines(): int
    {
        if (!is_file($this->queueFile)) {
            return 0;
        }
        $content = (string) file_get_contents($this->queueFile);
        if ($content === '') {
            return 0;
        }
        return count(array_filter(explode("\n", $content), static fn ($l) => $l !== ''));
    }
}

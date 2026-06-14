<?php

declare(strict_types=1);

use App\Config\SecretsConfig;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Cron\Utility\BatchScriptLauncher;
use App\Services\Cron\Enum\SyncOpenChatStateType as StateType;
use App\Services\Recommend\RecommendTagRebuildLock;
use PHPUnit\Framework\TestCase;

// docker compose exec app vendor/bin/phpunit app/Services/Recommend/test/RecommendTagRebuildLockTest.php
//
// 全再適用ロックの自己修復（漏れたロックの回収）を、状態リポジトリとランチャーを
// モック/スタブして検証する。kill された保持プロセスがフラグを下ろせず立ち残った状況を、
// 所有プロセスの死活と保持時間から検知して回収できることを確認する。
class RecommendTagRebuildLockTest extends TestCase
{
    protected function setUp(): void
    {
        SecretsConfig::$discordWebhookUrl = ''; // 回収時の通知を外に飛ばさない
    }

    /** フラグが立っていなければ保持されておらず、回収（setFalse）も死活確認もしない */
    public function test_not_held_when_flag_is_down(): void
    {
        $state = $this->createMock(SyncOpenChatStateRepositoryInterface::class);
        $state->method('getBool')->with(StateType::isRecommendTagRebuildActive)->willReturn(false);
        $state->expects($this->never())->method('setFalse');

        // フラグが下りていればプロセス死活すら確認しない（短絡）
        $launcher = $this->createMock(BatchScriptLauncher::class);
        $launcher->expects($this->never())->method('isAnyInstanceRunning');

        $this->assertFalse((new RecommendTagRebuildLock($state, $launcher))->isHeldByLiveProcess());
    }

    /** フラグが立ち・所有プロセス生存・取得時刻が新しい → 本当に実行中（回収しない） */
    public function test_held_when_process_alive_and_recent(): void
    {
        $state = $this->createMock(SyncOpenChatStateRepositoryInterface::class);
        $state->method('getBool')->willReturn(true);
        $state->method('getString')->willReturn(date('Y-m-d H:i:s')); // たった今取得
        $state->expects($this->never())->method('setFalse');

        $launcher = $this->createStub(BatchScriptLauncher::class);
        $launcher->method('isAnyInstanceRunning')->willReturn(true);

        $this->assertTrue((new RecommendTagRebuildLock($state, $launcher))->isHeldByLiveProcess());
    }

    /** フラグは立つが所有プロセスが居ない（= kill 等で漏れたロック）→ 回収して false */
    public function test_reclaims_leaked_lock_when_no_owner_process(): void
    {
        $state = $this->createMock(SyncOpenChatStateRepositoryInterface::class);
        $state->method('getBool')->willReturn(true);
        $state->method('getString')->willReturn(date('Y-m-d H:i:s'));
        $state->expects($this->once())->method('setFalse')->with(StateType::isRecommendTagRebuildActive);

        $launcher = $this->createStub(BatchScriptLauncher::class);
        $launcher->method('isAnyInstanceRunning')->willReturn(false); // どのスクリプトも生存していない

        $this->assertFalse((new RecommendTagRebuildLock($state, $launcher))->isHeldByLiveProcess());
    }

    /** 所有プロセスが生存と見えても、規定時間を大幅超過していれば保険で回収する */
    public function test_reclaims_when_held_too_long_even_if_process_seems_alive(): void
    {
        $state = $this->createMock(SyncOpenChatStateRepositoryInterface::class);
        $state->method('getBool')->willReturn(true);
        $state->method('getString')->willReturn(date('Y-m-d H:i:s', time() - 60 * 60 * 3)); // 3時間前
        $state->expects($this->once())->method('setFalse')->with(StateType::isRecommendTagRebuildActive);

        $launcher = $this->createStub(BatchScriptLauncher::class);
        $launcher->method('isAnyInstanceRunning')->willReturn(true);

        $this->assertFalse((new RecommendTagRebuildLock($state, $launcher))->isHeldByLiveProcess());
    }

    /** 取得時刻が記録されていない（旧形式/記録漏れ）も回収対象 */
    public function test_reclaims_when_started_at_missing(): void
    {
        $state = $this->createMock(SyncOpenChatStateRepositoryInterface::class);
        $state->method('getBool')->willReturn(true);
        $state->method('getString')->willReturn('');
        $state->expects($this->once())->method('setFalse')->with(StateType::isRecommendTagRebuildActive);

        $launcher = $this->createStub(BatchScriptLauncher::class);
        $launcher->method('isAnyInstanceRunning')->willReturn(true);

        $this->assertFalse((new RecommendTagRebuildLock($state, $launcher))->isHeldByLiveProcess());
    }

    /** acquire は取得時刻を記録しフラグを立てる */
    public function test_acquire_records_started_at_and_raises_flag(): void
    {
        $state = $this->createMock(SyncOpenChatStateRepositoryInterface::class);
        $state->expects($this->once())->method('setString')
            ->with(StateType::recommendTagRebuildStartedAt, $this->callback('is_string'));
        $state->expects($this->once())->method('setTrue')
            ->with(StateType::isRecommendTagRebuildActive);

        $launcher = $this->createStub(BatchScriptLauncher::class);

        (new RecommendTagRebuildLock($state, $launcher))->acquire();
    }

    /** release はフラグを下ろす */
    public function test_release_lowers_flag(): void
    {
        $state = $this->createMock(SyncOpenChatStateRepositoryInterface::class);
        $state->expects($this->once())->method('setFalse')
            ->with(StateType::isRecommendTagRebuildActive);

        $launcher = $this->createStub(BatchScriptLauncher::class);

        (new RecommendTagRebuildLock($state, $launcher))->release();
    }
}

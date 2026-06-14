<?php

declare(strict_types=1);

use App\Config\SecretsConfig;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Cron\Utility\BatchScriptLauncher;
use App\Services\Recommend\RebuildAllRecommendTagsService;
use App\Services\Recommend\RecommendTagRebuildLock;
use App\Services\Recommend\RecommendUpdater;
use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

// docker compose exec app vendor/bin/phpunit app/Services/Recommend/test/RebuildAllRecommendTagsServiceTest.php
//
// handle() の二重実行時の分岐だけを、依存を全てモックして検証する。
// 「実行中か」の判定は RecommendTagRebuildLock::isHeldByLiveProcess() に委譲しているため、
// ロックをモックして true/false を与え、分岐（待機 / kill再実行 / 通常実行）を確認する。
// 実DB再構築・CDN purge は走らせない（updater/launcher をモックして無効化）。
class RebuildAllRecommendTagsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        MimimalCmsConfig::$urlRoot = '';      // ja 経路（rebuildAllViaShadowSwap）
        SecretsConfig::$discordWebhookUrl = ''; // 通知を外に飛ばさない
    }

    /** 既定（待機）: 実行中なら kill も再構築もせず即 return する */
    public function test_default_waits_when_active(): void
    {
        $state = $this->createStub(SyncOpenChatStateRepositoryInterface::class);
        $updater = $this->createMock(RecommendUpdater::class);
        $launcher = $this->createMock(BatchScriptLauncher::class);
        $lock = $this->createMock(RecommendTagRebuildLock::class);

        // 生きたプロセスに本当に保持されている（漏れロックではない）
        $lock->method('isHeldByLiveProcess')->willReturn(true);

        // スキップ＝前ランを kill しない・再構築しない・連鎖しない・ロックを奪わない
        $launcher->expects($this->never())->method('killOtherInstances');
        $updater->expects($this->never())->method('rebuildAllViaShadowSwap');
        $launcher->expects($this->never())->method('launchSync');
        $lock->expects($this->never())->method('acquire');

        (new RebuildAllRecommendTagsService($state, $updater, $launcher, $lock))->handle(false);
    }

    /** cancelPrevious=true: 実行中なら前ランを kill して再構築→静的データ生成へ連鎖する */
    public function test_cancel_previous_kills_and_reruns_when_active(): void
    {
        $state = $this->createStub(SyncOpenChatStateRepositoryInterface::class);
        $updater = $this->createMock(RecommendUpdater::class);
        $launcher = $this->createMock(BatchScriptLauncher::class);
        $lock = $this->createMock(RecommendTagRebuildLock::class);

        $lock->method('isHeldByLiveProcess')->willReturn(true);

        $launcher->expects($this->once())->method('killOtherInstances')->willReturn('killed');
        $lock->expects($this->once())->method('acquire');
        $updater->expects($this->once())->method('rebuildAllViaShadowSwap');
        $launcher->expects($this->once())->method('launchSync'); // 静的データ生成へ連鎖

        (new RebuildAllRecommendTagsService($state, $updater, $launcher, $lock))->handle(true);
    }

    /** 実行中でなければ（漏れロックの自動回収後含む）、通常どおり再構築する（kill は呼ばない） */
    public function test_runs_normally_when_not_active(): void
    {
        $state = $this->createStub(SyncOpenChatStateRepositoryInterface::class);
        $updater = $this->createMock(RecommendUpdater::class);
        $launcher = $this->createMock(BatchScriptLauncher::class);
        $lock = $this->createMock(RecommendTagRebuildLock::class);

        $lock->method('isHeldByLiveProcess')->willReturn(false);

        $launcher->expects($this->never())->method('killOtherInstances');
        $lock->expects($this->once())->method('acquire');
        $updater->expects($this->once())->method('rebuildAllViaShadowSwap');
        $launcher->expects($this->once())->method('launchSync');

        (new RebuildAllRecommendTagsService($state, $updater, $launcher, $lock))->handle(false);
    }
}

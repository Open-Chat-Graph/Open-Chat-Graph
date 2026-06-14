<?php

declare(strict_types=1);

use App\Config\SecretsConfig;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Cron\Enum\SyncOpenChatStateType as StateType;
use App\Services\Cron\Utility\BatchScriptLauncher;
use App\Services\Recommend\RebuildAllRecommendTagsService;
use App\Services\Recommend\RecommendUpdater;
use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

// docker compose exec app vendor/bin/phpunit app/Services/Recommend/test/RebuildAllRecommendTagsServiceTest.php
//
// handle() の二重実行時の分岐だけを、依存を全てモックして検証する。
// 実DB再構築・CDN purge は走らせない（updater/launcher をモックして無効化）。
// Discord 通知も飛ばさない（webhook URL を空にして curl を no-op 化）。
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
        $state = $this->createMock(SyncOpenChatStateRepositoryInterface::class);
        $state->method('getBool')->with(StateType::isRecommendTagRebuildActive)->willReturn(true);

        $updater = $this->createMock(RecommendUpdater::class);
        $launcher = $this->createMock(BatchScriptLauncher::class);

        // スキップ＝前ランを kill しない・再構築しない・静的データ生成へ連鎖しない
        $launcher->expects($this->never())->method('killOtherInstances');
        $updater->expects($this->never())->method('rebuildAllViaShadowSwap');
        $launcher->expects($this->never())->method('launchSync');
        // 待機なので実行中フラグを立てない（前ランの所有を奪わない）
        $state->expects($this->never())->method('setTrue');

        (new RebuildAllRecommendTagsService($state, $updater, $launcher))->handle(false);
    }

    /** cancelPrevious=true: 実行中なら前ランを kill して再構築→静的データ生成へ連鎖する */
    public function test_cancel_previous_kills_and_reruns_when_active(): void
    {
        $state = $this->createMock(SyncOpenChatStateRepositoryInterface::class);
        $state->method('getBool')->with(StateType::isRecommendTagRebuildActive)->willReturn(true);

        $updater = $this->createMock(RecommendUpdater::class);
        $launcher = $this->createMock(BatchScriptLauncher::class);

        $launcher->expects($this->once())->method('killOtherInstances')->willReturn('killed');
        $updater->expects($this->once())->method('rebuildAllViaShadowSwap');
        $launcher->expects($this->once())->method('launchSync'); // 静的データ生成へ連鎖

        (new RebuildAllRecommendTagsService($state, $updater, $launcher))->handle(true);
    }

    /** 実行中でなければ、引数に関わらず通常どおり再構築する（kill は呼ばない） */
    public function test_runs_normally_when_not_active(): void
    {
        $state = $this->createMock(SyncOpenChatStateRepositoryInterface::class);
        $state->method('getBool')->with(StateType::isRecommendTagRebuildActive)->willReturn(false);

        $updater = $this->createMock(RecommendUpdater::class);
        $launcher = $this->createMock(BatchScriptLauncher::class);

        $launcher->expects($this->never())->method('killOtherInstances');
        $updater->expects($this->once())->method('rebuildAllViaShadowSwap');
        $launcher->expects($this->once())->method('launchSync');

        (new RebuildAllRecommendTagsService($state, $updater, $launcher))->handle(false);
    }
}

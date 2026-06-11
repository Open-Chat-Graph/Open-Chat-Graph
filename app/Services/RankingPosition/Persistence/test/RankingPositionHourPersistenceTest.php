<?php

declare(strict_types=1);

use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Cron\Utility\BatchScriptLauncher;
use App\Services\RankingPosition\Persistence\RankingPositionHourPersistence;
use App\Services\RankingPosition\Persistence\RankingPositionHourPersistenceProcess;
use PHPUnit\Framework\TestCase;

// docker compose exec app ./vendor/bin/phpunit app/Services/RankingPosition/Persistence/test/RankingPositionHourPersistenceTest.php
class RankingPositionHourPersistenceTest extends TestCase
{
    /**
     * 正常系：全カテゴリが1サイクルで完了する場合
     */
    public function test_persistAllCategoriesBackground_success()
    {
        // モックを作成
        $processMock = $this->createMock(RankingPositionHourPersistenceProcess::class);
        $repositoryMock = $this->createMock(RankingPositionHourRepositoryInterface::class);
        $stateMock = $this->createStub(SyncOpenChatStateRepositoryInterface::class);
        $launcherMock = $this->createMock(BatchScriptLauncher::class);

        // 親プロセス生存確認用のstateは空（parentPid未登録）を返す
        $stateMock->method('getArray')->willReturn([]);

        // 1サイクル目で全完了を返す
        $processMock->expects($this->once())
            ->method('processOneCycle')
            ->with($this->isString(), $this->isString())
            ->willReturn(true);

        $repositoryMock->expects($this->once())
            ->method('insertTotalCount')
            ->willReturn(['total_count_all_category_rising' => 100, 'total_count_all_category_ranking' => 200]);

        $repositoryMock->expects($this->once())
            ->method('delete');

        // 正常完了時はタイムアウト時のcron再実行（バッチスクリプト起動）が行われないこと
        $launcherMock->expects($this->never())
            ->method('launchInBackground');

        // インスタンス作成して実行
        $instance = new RankingPositionHourPersistence($processMock, $repositoryMock, $stateMock, $launcherMock);
        $instance->persistAllCategoriesBackground();

        // 例外が発生せず完了すればOK
        $this->assertTrue(true);
    }

    /**
     * 正常系：複数サイクルで完了する場合
     */
    public function test_persistAllCategoriesBackground_multiple_cycles()
    {
        // モックを作成
        $processMock = $this->createMock(RankingPositionHourPersistenceProcess::class);
        $repositoryMock = $this->createMock(RankingPositionHourRepositoryInterface::class);
        $stateMock = $this->createStub(SyncOpenChatStateRepositoryInterface::class);
        $launcherMock = $this->createMock(BatchScriptLauncher::class);

        // 親プロセス生存確認用のstateは空（parentPid未登録）を返す
        $stateMock->method('getArray')->willReturn([]);

        // 3サイクル目で完了
        $processMock->expects($this->exactly(3))
            ->method('processOneCycle')
            ->with($this->isString(), $this->isString())
            ->willReturnOnConsecutiveCalls(false, false, true);

        $repositoryMock->expects($this->once())
            ->method('insertTotalCount')
            ->willReturn(['total_count_all_category_rising' => 100, 'total_count_all_category_ranking' => 200]);

        $repositoryMock->expects($this->once())
            ->method('delete');

        // 正常完了時はタイムアウト時のcron再実行（バッチスクリプト起動）が行われないこと
        $launcherMock->expects($this->never())
            ->method('launchInBackground');

        // インスタンス作成して実行
        $instance = new RankingPositionHourPersistence($processMock, $repositoryMock, $stateMock, $launcherMock);
        $instance->persistAllCategoriesBackground();

        $this->assertTrue(true);
    }
}

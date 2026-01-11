<?php

declare(strict_types=1);

use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
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
        $stateMock = $this->createMock(SyncOpenChatStateRepositoryInterface::class);

        // モックの期待値を設定
        $processMock->expects($this->once())
            ->method('initializeCache');

        // 1サイクル目で全完了を返す
        $processMock->expects($this->once())
            ->method('processOneCycle')
            ->with($this->isType('string'))
            ->willReturn(true);

        $processMock->expects($this->once())
            ->method('afterClearCache');

        $repositoryMock->expects($this->once())
            ->method('insertTotalCount');

        $repositoryMock->expects($this->once())
            ->method('delete');

        // インスタンス作成して実行
        $instance = new RankingPositionHourPersistence($processMock, $repositoryMock, $stateMock);
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
        $stateMock = $this->createMock(SyncOpenChatStateRepositoryInterface::class);

        $processMock->expects($this->once())
            ->method('initializeCache');

        // 3サイクル目で完了
        $processMock->expects($this->exactly(3))
            ->method('processOneCycle')
            ->with($this->isType('string'))
            ->willReturnOnConsecutiveCalls(false, false, true);

        $processMock->expects($this->once())
            ->method('afterClearCache');

        // インスタンス作成して実行
        $instance = new RankingPositionHourPersistence($processMock, $repositoryMock, $stateMock);
        $instance->persistAllCategoriesBackground();

        $this->assertTrue(true);
    }
}

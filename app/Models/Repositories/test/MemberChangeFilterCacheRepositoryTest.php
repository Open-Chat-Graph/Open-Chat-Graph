<?php

/**
 * MemberChangeFilterCacheRepository のテスト
 *
 * 実行コマンド:
 * docker compose exec app ./vendor/bin/phpunit app/Models/Repositories/test/MemberChangeFilterCacheRepositoryTest.php
 */

use PHPUnit\Framework\TestCase;
use App\Models\Repositories\MemberChangeFilterCacheRepository;
use App\Models\Repositories\MemberChangeFilterCacheRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;
use App\Config\AppConfig;

class MemberChangeFilterCacheRepositoryTest extends TestCase
{
    private MemberChangeFilterCacheRepository $repository;
    private string $filterMemberChangePath;
    private string $filterNewRoomsPath;
    private string $filterWeeklyUpdatePath;
    private string $filterDatePath;

    private ?string $originalMemberChange = null;
    private ?string $originalNewRooms = null;
    private ?string $originalWeeklyUpdate = null;
    private ?string $originalDate = null;

    protected function setUp(): void
    {
        // モックを作成
        $statisticsRepository = $this->createMock(StatisticsRepositoryInterface::class);

        // 各メソッドの戻り値を設定
        $statisticsRepository->method('getMemberChangeWithinLastWeek')
            ->willReturn([100, 200, 300]);
        $statisticsRepository->method('getNewRoomsWithLessThan8Records')
            ->willReturn([400, 500]);
        $statisticsRepository->method('getWeeklyUpdateRooms')
            ->willReturn([600, 700]);

        $this->repository = new MemberChangeFilterCacheRepository($statisticsRepository);

        $this->filterMemberChangePath = AppConfig::getStorageFilePath('filterMemberChange');
        $this->filterNewRoomsPath = AppConfig::getStorageFilePath('filterNewRooms');
        $this->filterWeeklyUpdatePath = AppConfig::getStorageFilePath('filterWeeklyUpdate');
        $this->filterDatePath = AppConfig::getStorageFilePath('openChatHourFilterIdDate');

        // 既存のファイルをバックアップ
        $this->backupFile($this->filterMemberChangePath, $this->originalMemberChange);
        $this->backupFile($this->filterNewRoomsPath, $this->originalNewRooms);
        $this->backupFile($this->filterWeeklyUpdatePath, $this->originalWeeklyUpdate);
        $this->backupFile($this->filterDatePath, $this->originalDate);
    }

    private function backupFile(string $path, ?string &$backup): void
    {
        if (file_exists($path)) {
            $backup = file_get_contents($path);
        }
    }

    protected function tearDown(): void
    {
        // 既存のファイルを復元
        $this->restoreFile($this->filterMemberChangePath, $this->originalMemberChange);
        $this->restoreFile($this->filterNewRoomsPath, $this->originalNewRooms);
        $this->restoreFile($this->filterWeeklyUpdatePath, $this->originalWeeklyUpdate);
        $this->restoreFile($this->filterDatePath, $this->originalDate);
    }

    private function restoreFile(string $path, ?string $backup): void
    {
        if ($backup !== null) {
            file_put_contents($path, $backup);
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }

    private function clearAllCaches(): void
    {
        foreach ([
            $this->filterMemberChangePath,
            $this->filterNewRoomsPath,
            $this->filterWeeklyUpdatePath,
            $this->filterDatePath
        ] as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    // ========================================
    // getForHourly のテスト
    // ========================================

    public function test_getForHourly_returns_memberChange_cached_and_newRooms_realtime(): void
    {
        $this->clearAllCaches();
        $date = '2025-01-01';

        $result = $this->repository->getForHourly($date);

        // 変動がある部屋[100,200,300] + 新規部屋[400,500]
        sort($result);
        $this->assertEquals([100, 200, 300, 400, 500], $result);

        // キャッシュファイルが作成される
        $this->assertFileExists($this->filterMemberChangePath);
        $this->assertFileExists($this->filterDatePath);

        // 新規部屋もキャッシュされる（リアルタイム取得後にキャッシュ更新）
        $this->assertFileExists($this->filterNewRoomsPath);
    }

    public function test_getForHourly_uses_cache_for_memberChange(): void
    {
        $date = '2025-01-01';
        $cachedData = [1, 2, 3];

        // キャッシュを手動で作成
        saveSerializedFile($this->filterMemberChangePath, $cachedData);
        safeFileRewrite($this->filterDatePath, $date);

        $result = $this->repository->getForHourly($date);

        // キャッシュ[1,2,3] + 新規部屋（リアルタイム）[400,500]
        sort($result);
        $this->assertEquals([1, 2, 3, 400, 500], $result);
    }

    // ========================================
    // getForDaily のテスト
    // ========================================

    public function test_getForDaily_returns_all_three_data(): void
    {
        $this->clearAllCaches();
        $date = '2025-01-01';

        $result = $this->repository->getForDaily($date);

        // 変動がある部屋[100,200,300] + 新規部屋[400,500] + 週次更新[600,700]
        sort($result);
        $this->assertEquals([100, 200, 300, 400, 500, 600, 700], $result);

        // 全てのキャッシュファイルが作成される
        $this->assertFileExists($this->filterMemberChangePath);
        $this->assertFileExists($this->filterNewRoomsPath);
        $this->assertFileExists($this->filterWeeklyUpdatePath);
        $this->assertFileExists($this->filterDatePath);
    }

    public function test_getForDaily_uses_all_caches_on_second_call(): void
    {
        $date = '2025-01-01';

        // 全てのキャッシュを手動で作成
        saveSerializedFile($this->filterMemberChangePath, [10, 20]);
        saveSerializedFile($this->filterNewRoomsPath, [30, 40]);
        saveSerializedFile($this->filterWeeklyUpdatePath, [50, 60]);
        safeFileRewrite($this->filterDatePath, $date);

        $result = $this->repository->getForDaily($date);

        // 全てキャッシュから取得
        sort($result);
        $this->assertEquals([10, 20, 30, 40, 50, 60], $result);
    }

    public function test_getForDaily_refetches_when_date_mismatch(): void
    {
        // 別の日付でキャッシュを作成
        saveSerializedFile($this->filterMemberChangePath, [1, 2, 3]);
        safeFileRewrite($this->filterDatePath, '2025-01-01');

        // 異なる日付でgetForDaily
        $result = $this->repository->getForDaily('2025-01-02');

        // DBから再取得した値が返される
        sort($result);
        $this->assertEquals([100, 200, 300, 400, 500, 600, 700], $result);

        // 日付が更新される
        $this->assertEquals('2025-01-02', file_get_contents($this->filterDatePath));
    }

    // ========================================
    // インターフェース・DI のテスト
    // ========================================

    public function test_implements_interface(): void
    {
        $this->assertInstanceOf(MemberChangeFilterCacheRepositoryInterface::class, $this->repository);
    }

    public function test_di_creates_instance(): void
    {
        /** @var MemberChangeFilterCacheRepositoryInterface $repository */
        $repository = app(MemberChangeFilterCacheRepositoryInterface::class);

        $this->assertInstanceOf(MemberChangeFilterCacheRepository::class, $repository);
    }

    // ========================================
    // 値の妥当性テスト
    // ========================================

    public function test_getForHourly_returns_unique_values(): void
    {
        $this->clearAllCaches();
        $result = $this->repository->getForHourly('2025-01-01');

        // 重複がないことを確認
        $this->assertEquals(count($result), count(array_unique($result)));
    }

    public function test_getForDaily_returns_unique_values(): void
    {
        $this->clearAllCaches();
        $result = $this->repository->getForDaily('2025-01-01');

        // 重複がないことを確認
        $this->assertEquals(count($result), count(array_unique($result)));
    }

    public function test_getForDaily_includes_weekly_update_rooms(): void
    {
        $this->clearAllCaches();
        $result = $this->repository->getForDaily('2025-01-01');

        // 週次更新部屋（600, 700）が含まれていることを確認
        $this->assertContains(600, $result);
        $this->assertContains(700, $result);
    }

    public function test_getForHourly_does_not_include_weekly_update_rooms(): void
    {
        $this->clearAllCaches();
        $result = $this->repository->getForHourly('2025-01-01');

        // 週次更新部屋（600, 700）が含まれていないことを確認
        $this->assertNotContains(600, $result);
        $this->assertNotContains(700, $result);
    }
}

<?php

/**
 * MemberChangeFilterCacheRepository のテスト
 *
 * 実行コマンド:
 * docker compose exec app ./vendor/bin/phpunit app/Models/Repositories/test/MemberChangeFilterCacheRepositoryTest.php
 *
 * テスト内容:
 * - 実際のSQLiteデータベースを使用（1200件以上のテストデータ）
 * - キャッシュの読み書き動作
 * - hourly/dailyの使い分け
 * - キーごとに独立した日付検証
 */

use PHPUnit\Framework\TestCase;
use App\Models\Repositories\MemberChangeFilterCacheRepository;
use App\Models\Repositories\MemberChangeFilterCacheRepositoryInterface;
use App\Models\SQLite\Repositories\Statistics\SqliteStatisticsRepository;
use App\Models\SQLite\SQLiteStatistics;
use App\Services\Storage\FileStorageInterface;

class MemberChangeFilterCacheRepositoryTest extends TestCase
{
    private MemberChangeFilterCacheRepository $repository;
    private FileStorageInterface $fileStorage;

    private string $filterMemberChangePath;
    private string $filterNewRoomsPath;
    private string $filterWeeklyUpdatePath;

    private ?string $originalMemberChange = null;
    private ?string $originalNewRooms = null;
    private ?string $originalWeeklyUpdate = null;

    /**
     * テストデータの期待値（クラス全体で共有）
     */
    private static string $today;
    private static string $tempDbFile = '';
    private static array $expectedMemberChange = [];
    private static array $expectedNewRooms = [];
    private static array $expectedWeeklyUpdate = [];

    /**
     * クラス全体で1回だけDB作成（テスト高速化）
     */
    public static function setUpBeforeClass(): void
    {
        self::$today = date('Y-m-d');
        self::$tempDbFile = sys_get_temp_dir() . '/test_filter_cache_' . uniqid() . '.db';

        SQLiteStatistics::$pdo = new \PDO('sqlite:' . self::$tempDbFile);
        SQLiteStatistics::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        SQLiteStatistics::$pdo->exec("
            CREATE TABLE statistics (
                open_chat_id INTEGER NOT NULL,
                member INTEGER NOT NULL,
                date TEXT NOT NULL,
                PRIMARY KEY (open_chat_id, date)
            )
        ");

        self::insertTestData();
    }

    public static function tearDownAfterClass(): void
    {
        if (file_exists(self::$tempDbFile)) {
            unlink(self::$tempDbFile);
        }
    }

    protected function setUp(): void
    {
        // PDOを再セット（他のテストで変わっている可能性）
        SQLiteStatistics::$pdo = new \PDO('sqlite:' . self::$tempDbFile);
        SQLiteStatistics::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $statisticsRepository = app(SqliteStatisticsRepository::class);
        $this->fileStorage = app(FileStorageInterface::class);
        $this->repository = new MemberChangeFilterCacheRepository(
            $statisticsRepository,
            $this->fileStorage
        );

        $this->filterMemberChangePath = $this->fileStorage->getStorageFilePath('filterMemberChange');
        $this->filterNewRoomsPath = $this->fileStorage->getStorageFilePath('filterNewRooms');
        $this->filterWeeklyUpdatePath = $this->fileStorage->getStorageFilePath('filterWeeklyUpdate');

        $this->backupFile($this->filterMemberChangePath, $this->originalMemberChange);
        $this->backupFile($this->filterNewRoomsPath, $this->originalNewRooms);
        $this->backupFile($this->filterWeeklyUpdatePath, $this->originalWeeklyUpdate);

        $this->clearAllCaches();
    }

    private function backupFile(string $path, ?string &$backup): void
    {
        if (file_exists($path)) {
            $backup = file_get_contents($path);
        }
    }

    protected function tearDown(): void
    {
        $this->restoreFile($this->filterMemberChangePath, $this->originalMemberChange);
        $this->restoreFile($this->filterNewRoomsPath, $this->originalNewRooms);
        $this->restoreFile($this->filterWeeklyUpdatePath, $this->originalWeeklyUpdate);
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
            $this->filterWeeklyUpdatePath
        ] as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * 新形式のキャッシュファイルを書き込む（日付埋め込み）
     */
    private function writeCacheFile(string $key, string $date, array $data): void
    {
        $this->fileStorage->saveSerializedFile('@' . $key, [
            '_cacheDate' => $date,
            '_cacheData' => $data,
        ]);
    }

    /**
     * テストデータを挿入（1200件以上）
     *
     * データパターン:
     * - ID 1-200: メンバー変動あり（過去8日間で変動）→ expectedMemberChange
     * - ID 201-400: 新規部屋（レコード数8未満）→ expectedNewRooms
     * - ID 401-600: 週次更新対象（最終レコードが1週間以上前）→ expectedWeeklyUpdate
     * - ID 601-800: 通常部屋（対象外）
     * - ID 801-1000: メンバー変動あり + 新規部屋（両方に該当）
     * - ID 1001-1200: 確認クロール対象（8日以上のギャップ後に復帰）→ expectedWeeklyUpdate
     */
    private static function insertTestData(): void
    {
        $stmt = SQLiteStatistics::$pdo->prepare(
            "INSERT INTO statistics (open_chat_id, member, date) VALUES (?, ?, ?)"
        );

        // パターン1: メンバー変動あり（ID 1-200）
        // 過去8日間でメンバー数が変動している
        for ($id = 1; $id <= 200; $id++) {
            $baseMember = 100 + $id;
            for ($day = 8; $day >= 0; $day--) {
                $date = date('Y-m-d', strtotime("-{$day} days"));
                // 日が進むにつれてメンバー数が増加
                $member = $baseMember + (8 - $day) * 5;
                $stmt->execute([$id, $member, $date]);
            }
            self::$expectedMemberChange[] = $id;
        }

        // パターン2: 新規部屋（ID 201-400）
        // レコード数が8未満（5日分のみ）
        for ($id = 201; $id <= 400; $id++) {
            $baseMember = 50 + ($id - 200);
            for ($day = 4; $day >= 0; $day--) {
                $date = date('Y-m-d', strtotime("-{$day} days"));
                $stmt->execute([$id, $baseMember, $date]);
            }
            self::$expectedNewRooms[] = $id;
        }

        // パターン3: 週次更新対象（ID 401-600）
        // 最終レコードが1週間以上前
        for ($id = 401; $id <= 600; $id++) {
            $baseMember = 200 + ($id - 400);
            for ($day = 15; $day >= 8; $day--) {
                $date = date('Y-m-d', strtotime("-{$day} days"));
                $member = $baseMember + (15 - $day) * 3;
                $stmt->execute([$id, $member, $date]);
            }
            self::$expectedWeeklyUpdate[] = $id;
        }

        // パターン4: 通常部屋（ID 601-800）
        // 9日分のレコード、メンバー変動なし、最終更新が今日
        for ($id = 601; $id <= 800; $id++) {
            $baseMember = 500 + ($id - 600);
            for ($day = 8; $day >= 0; $day--) {
                $date = date('Y-m-d', strtotime("-{$day} days"));
                $stmt->execute([$id, $baseMember, $date]); // メンバー数固定
            }
            // このパターンはどのフィルターにも該当しない
        }

        // パターン5: メンバー変動あり + 新規部屋（ID 801-1000）
        // レコード数が8未満かつメンバー変動あり
        for ($id = 801; $id <= 1000; $id++) {
            $baseMember = 30 + ($id - 800);
            for ($day = 4; $day >= 0; $day--) {
                $date = date('Y-m-d', strtotime("-{$day} days"));
                $member = $baseMember + (4 - $day) * 2; // 変動あり
                $stmt->execute([$id, $member, $date]);
            }
            self::$expectedMemberChange[] = $id;
            self::$expectedNewRooms[] = $id;
        }

        // パターン6: 確認クロール対象（ID 1001-1200）
        // 古いレコード8件（15〜22日前） + 昨日のレコード1件
        // 直近8日間のレコードは昨日の1件のみ → 8日以上のギャップ後の復帰 → 確認クロール対象
        // レコード数9件 ≥ 8 → getNewRoomsWithLessThan8Records には該当しない
        for ($id = 1001; $id <= 1200; $id++) {
            $baseMember = 700 + ($id - 1000);
            for ($day = 22; $day >= 15; $day--) {
                $date = date('Y-m-d', strtotime("-{$day} days"));
                $stmt->execute([$id, $baseMember, $date]);
            }
            $stmt->execute([$id, $baseMember + 10, date('Y-m-d', strtotime('-1 days'))]);
            self::$expectedWeeklyUpdate[] = $id;
        }

        // 期待値をソート
        sort(self::$expectedMemberChange);
        sort(self::$expectedNewRooms);
        sort(self::$expectedWeeklyUpdate);
    }

    // ========================================
    // データ量の検証
    // ========================================

    public function test_database_has_over_1000_records(): void
    {
        $stmt = SQLiteStatistics::$pdo->query("SELECT COUNT(*) FROM statistics");
        $count = (int) $stmt->fetchColumn();

        $this->assertGreaterThan(1000, $count, 'テストデータは1000件以上あること');
    }

    public function test_database_has_expected_room_count(): void
    {
        $stmt = SQLiteStatistics::$pdo->query(
            "SELECT COUNT(DISTINCT open_chat_id) FROM statistics"
        );
        $count = (int) $stmt->fetchColumn();

        $this->assertSame(1200, $count, '部屋数は1200件であること');
    }

    // ========================================
    // getForHourly のテスト
    // ========================================

    public function test_getForHourly_returns_memberChange_and_newRooms(): void
    {
        $result = $this->repository->getForHourly(self::$today);
        sort($result);

        // メンバー変動 + 新規部屋（重複除去）
        $expected = array_unique(array_merge(
            self::$expectedMemberChange,
            self::$expectedNewRooms
        ));
        sort($expected);

        $this->assertSame($expected, $result);
    }

    public function test_getForHourly_creates_cache_files(): void
    {
        $this->repository->getForHourly(self::$today);

        // キャッシュファイルが作成される
        $this->assertFileExists($this->filterMemberChangePath);
        $this->assertFileExists($this->filterNewRoomsPath);
    }

    public function test_getForHourly_uses_cache_for_memberChange(): void
    {
        // 1回目: DBから取得してキャッシュ
        $this->repository->getForHourly(self::$today);

        // キャッシュを手動で変更（新形式で日付を埋め込み）
        $this->writeCacheFile('filterMemberChange', self::$today, [9999]);

        // 2回目: memberChangeはキャッシュから、newRoomsはリアルタイム
        $result2 = $this->repository->getForHourly(self::$today);
        sort($result2);

        // キャッシュ[9999] + 新規部屋（リアルタイム）
        $expected = array_unique(array_merge([9999], self::$expectedNewRooms));
        sort($expected);

        $this->assertSame($expected, $result2);
    }

    public function test_getForHourly_does_not_include_weekly_update(): void
    {
        $result = $this->repository->getForHourly(self::$today);

        // 週次更新部屋は含まれない
        foreach (self::$expectedWeeklyUpdate as $id) {
            $this->assertNotContains(
                $id,
                $result,
                "週次更新部屋（ID: {$id}）はhourlyに含まれないこと"
            );
        }
    }

    // ========================================
    // getForDaily のテスト
    // ========================================

    public function test_getForDaily_returns_all_three_data(): void
    {
        $result = $this->repository->getForDaily(self::$today);
        sort($result);

        // メンバー変動 + 新規部屋 + 週次更新（重複除去）
        $expected = array_unique(array_merge(
            self::$expectedMemberChange,
            self::$expectedNewRooms,
            self::$expectedWeeklyUpdate
        ));
        sort($expected);

        $this->assertSame($expected, $result);
    }

    public function test_getForDaily_creates_all_cache_files(): void
    {
        $this->repository->getForDaily(self::$today);

        // 全てのキャッシュファイルが作成される
        $this->assertFileExists($this->filterMemberChangePath);
        $this->assertFileExists($this->filterNewRoomsPath);
        $this->assertFileExists($this->filterWeeklyUpdatePath);
    }

    public function test_getForDaily_uses_all_caches_on_second_call(): void
    {
        // 1回目: DBから取得してキャッシュ
        $this->repository->getForDaily(self::$today);

        // 全てのキャッシュを手動で変更（新形式）
        $this->writeCacheFile('filterMemberChange', self::$today, [1]);
        $this->writeCacheFile('filterNewRooms', self::$today, [2]);
        $this->writeCacheFile('filterWeeklyUpdate', self::$today, [3]);

        // 2回目: 全てキャッシュから
        $result = $this->repository->getForDaily(self::$today);
        sort($result);

        $this->assertSame([1, 2, 3], $result);
    }

    public function test_getForDaily_refetches_when_date_mismatch(): void
    {
        // 別の日付でキャッシュを作成（新形式）
        $this->writeCacheFile('filterMemberChange', '2020-01-01', [9999]);

        // 異なる日付でgetForDaily
        $result = $this->repository->getForDaily(self::$today);
        sort($result);

        // DBから再取得した値が返される
        $expected = array_unique(array_merge(
            self::$expectedMemberChange,
            self::$expectedNewRooms,
            self::$expectedWeeklyUpdate
        ));
        sort($expected);

        $this->assertSame($expected, $result);
    }

    public function test_getForDaily_includes_weekly_update(): void
    {
        $result = $this->repository->getForDaily(self::$today);

        // 週次更新部屋が含まれる
        foreach (self::$expectedWeeklyUpdate as $id) {
            $this->assertContains(
                $id,
                $result,
                "週次更新部屋（ID: {$id}）がdailyに含まれること"
            );
        }
    }

    // ========================================
    // キャッシュ日付の独立性テスト
    // ========================================

    public function test_hourly_cache_does_not_validate_weekly_cache(): void
    {
        // hourlyタスクがfilterMemberChangeとfilterNewRoomsのキャッシュを今日の日付で保存
        $this->repository->getForHourly(self::$today);

        // filterWeeklyUpdateに古い日付のキャッシュを手動作成
        $this->writeCacheFile('filterWeeklyUpdate', '2020-01-01', [9999]);

        // getForDailyを呼ぶ → filterWeeklyUpdateの日付が不一致なのでDBから再取得すべき
        $result = $this->repository->getForDaily(self::$today);

        // 古い[9999]ではなく、DBから取得した正しい週次更新部屋が含まれること
        foreach (self::$expectedWeeklyUpdate as $id) {
            $this->assertContains(
                $id,
                $result,
                "週次更新部屋（ID: {$id}）がDBから再取得されてdailyに含まれること"
            );
        }
        $this->assertNotContains(9999, $result, '古いキャッシュの値が使われないこと');
    }

    public function test_old_format_cache_is_treated_as_invalid(): void
    {
        // 旧形式のキャッシュファイル（日付埋め込みなし）を作成
        saveSerializedFile($this->filterMemberChangePath, [9999]);

        // 旧形式はキャッシュ無効として扱われ、DBから再取得される
        $result = $this->repository->getForHourly(self::$today);
        sort($result);

        $expected = array_unique(array_merge(
            self::$expectedMemberChange,
            self::$expectedNewRooms
        ));
        sort($expected);

        $this->assertSame($expected, $result);
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
        $result = $this->repository->getForHourly(self::$today);

        // 重複がないことを確認
        $this->assertCount(count(array_unique($result)), $result);
    }

    public function test_getForDaily_returns_unique_values(): void
    {
        $result = $this->repository->getForDaily(self::$today);

        // 重複がないことを確認
        $this->assertCount(count(array_unique($result)), $result);
    }

    // ========================================
    // パフォーマンステスト
    // ========================================

    public function test_getForHourly_performance(): void
    {
        $start = microtime(true);
        $this->repository->getForHourly(self::$today);
        $elapsed = microtime(true) - $start;

        // 1秒以内に完了すること
        $this->assertLessThan(1.0, $elapsed, 'getForHourlyは1秒以内に完了すること');
    }

    public function test_getForDaily_performance(): void
    {
        $start = microtime(true);
        $this->repository->getForDaily(self::$today);
        $elapsed = microtime(true) - $start;

        // 1秒以内に完了すること
        $this->assertLessThan(1.0, $elapsed, 'getForDailyは1秒以内に完了すること');
    }

    public function test_cached_call_is_faster(): void
    {
        // 1回目: DBから取得
        $start1 = microtime(true);
        $this->repository->getForDaily(self::$today);
        $elapsed1 = microtime(true) - $start1;

        // 2回目: キャッシュから取得
        $start2 = microtime(true);
        $this->repository->getForDaily(self::$today);
        $elapsed2 = microtime(true) - $start2;

        // キャッシュからの取得は初回より速いこと
        $this->assertLessThan($elapsed1, $elapsed2, 'キャッシュからの取得は初回より速いこと');
    }
}

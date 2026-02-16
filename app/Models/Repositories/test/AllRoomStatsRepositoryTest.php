<?php

/**
 * AllRoomStatsRepository のテスト
 *
 * 実行コマンド:
 * docker compose exec app ./vendor/bin/phpunit app/Models/Repositories/test/AllRoomStatsRepositoryTest.php
 *
 * テスト内容:
 * - 各統計クエリの結果を直接SQLの結果と照合して正しさを検証
 * - カテゴリー別統計の構造・整合性を検証
 * - 削除済みルーム数の期間別フィルタリングの正しさを検証
 * - 参加者分布・中央値の構造検証
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Models\Repositories\AllRoomStatsRepository;
use App\Models\Repositories\DB;

class AllRoomStatsRepositoryTest extends TestCase
{
    private AllRoomStatsRepository $repository;

    protected function setUp(): void
    {
        DB::connect();
        $this->repository = new AllRoomStatsRepository();
    }

    /**
     * 直接SQLで期待値を取得するヘルパー
     */
    private function queryInt(string $sql): int
    {
        return (int) DB::$pdo->query($sql)->fetchColumn();
    }

    /**
     * getTotalRoomCount() が open_chat テーブルの COUNT(*) と一致することを検証
     */
    public function test_getTotalRoomCount_matches_direct_query(): void
    {
        $expected = $this->queryInt('SELECT COUNT(*) FROM open_chat');
        $actual = $this->repository->getTotalRoomCount();

        $this->assertSame($expected, $actual);
    }

    /**
     * getTotalMemberCount() が open_chat テーブルの SUM(member) と一致することを検証
     */
    public function test_getTotalMemberCount_matches_direct_query(): void
    {
        $expected = $this->queryInt('SELECT SUM(member) FROM open_chat');
        $actual = $this->repository->getTotalMemberCount();

        $this->assertSame($expected, $actual);
    }

    /**
     * getTrackingStartDate() が open_chat テーブルの MIN(created_at) と一致することを検証
     * データが存在しない場合はnullが返る
     */
    public function test_getTrackingStartDate_matches_direct_query(): void
    {
        $result = DB::$pdo->query('SELECT MIN(created_at) FROM open_chat')->fetchColumn();
        $expected = $result !== false ? (string) $result : null;
        $actual = $this->repository->getTrackingStartDate();

        $this->assertSame($expected, $actual);
    }

    /**
     * getNewRoomCountSince('1 hour') が直近1時間以内に登録されたルーム数と一致することを検証
     */
    public function test_getNewRoomCountSince_hourly_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COUNT(*) FROM open_chat WHERE created_at >= NOW() - INTERVAL 1 HOUR'
        );
        $actual = $this->repository->getNewRoomCountSince('1 hour');

        $this->assertSame($expected, $actual);
    }

    /**
     * getNewRoomCountSince('24 hour') が直近24時間以内に登録されたルーム数と一致することを検証
     */
    public function test_getNewRoomCountSince_daily_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COUNT(*) FROM open_chat WHERE created_at >= NOW() - INTERVAL 24 HOUR'
        );
        $actual = $this->repository->getNewRoomCountSince('24 hour');

        $this->assertSame($expected, $actual);
    }

    /**
     * getNewRoomCountSince('7 day') が直近1週間以内に登録されたルーム数と一致することを検証
     */
    public function test_getNewRoomCountSince_weekly_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COUNT(*) FROM open_chat WHERE created_at >= NOW() - INTERVAL 7 DAY'
        );
        $actual = $this->repository->getNewRoomCountSince('7 day');

        $this->assertSame($expected, $actual);
    }

    /**
     * getNewRoomCountSince('1 month') が直近1ヶ月以内に登録されたルーム数と一致することを検証
     */
    public function test_getNewRoomCountSince_monthly_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COUNT(*) FROM open_chat WHERE created_at >= NOW() - INTERVAL 1 MONTH'
        );
        $actual = $this->repository->getNewRoomCountSince('1 month');

        $this->assertSame($expected, $actual);
    }

    /**
     * 新規登録ルーム数の期間別件数が整合していることを検証
     * 1時間 <= 24時間 <= 1週間 <= 1ヶ月 <= 全件数 の順で件数が増えること
     */
    public function test_getNewRoomCountSince_ordering(): void
    {
        $total = $this->repository->getTotalRoomCount();
        $monthly = $this->repository->getNewRoomCountSince('1 month');
        $weekly = $this->repository->getNewRoomCountSince('7 day');
        $daily = $this->repository->getNewRoomCountSince('24 hour');
        $hourly = $this->repository->getNewRoomCountSince('1 hour');

        $this->assertLessThanOrEqual($total, $monthly);
        $this->assertLessThanOrEqual($monthly, $weekly);
        $this->assertLessThanOrEqual($weekly, $daily);
        $this->assertLessThanOrEqual($daily, $hourly);
    }

    /**
     * getDeletedRoomCountSince('1 hour') が直近1時間以内に削除されたルーム数と一致することを検証
     */
    public function test_getDeletedRoomCountSince_hourly_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COUNT(*) FROM open_chat_deleted WHERE deleted_at >= NOW() - INTERVAL 1 HOUR'
        );
        $actual = $this->repository->getDeletedRoomCountSince('1 hour');

        $this->assertSame($expected, $actual);
    }

    /**
     * getDeletedRoomCountSince('24 hour') が直近24時間以内に削除されたルーム数と一致することを検証
     */
    public function test_getDeletedRoomCountSince_daily_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COUNT(*) FROM open_chat_deleted WHERE deleted_at >= NOW() - INTERVAL 24 HOUR'
        );
        $actual = $this->repository->getDeletedRoomCountSince('24 hour');

        $this->assertSame($expected, $actual);
    }

    /**
     * getDeletedRoomCountSince('7 day') が直近1週間以内に削除されたルーム数と一致することを検証
     */
    public function test_getDeletedRoomCountSince_weekly_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COUNT(*) FROM open_chat_deleted WHERE deleted_at >= NOW() - INTERVAL 7 DAY'
        );
        $actual = $this->repository->getDeletedRoomCountSince('7 day');

        $this->assertSame($expected, $actual);
    }

    /**
     * getDeletedRoomCountSince('1 month') が直近1ヶ月以内に削除されたルーム数と一致することを検証
     */
    public function test_getDeletedRoomCountSince_monthly_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COUNT(*) FROM open_chat_deleted WHERE deleted_at >= NOW() - INTERVAL 1 MONTH'
        );
        $actual = $this->repository->getDeletedRoomCountSince('1 month');

        $this->assertSame($expected, $actual);
    }

    /**
     * 削除済みルーム数の期間別件数が整合していることを検証
     * 1時間 <= 24時間 <= 1週間 <= 1ヶ月 の順で件数が増えること
     */
    public function test_getDeletedRoomCountSince_ordering(): void
    {
        $monthly = $this->repository->getDeletedRoomCountSince('1 month');
        $hourly = $this->repository->getDeletedRoomCountSince('1 hour');
        $daily = $this->repository->getDeletedRoomCountSince('24 hour');
        $weekly = $this->repository->getDeletedRoomCountSince('7 day');

        $this->assertLessThanOrEqual($monthly, $weekly);
        $this->assertLessThanOrEqual($weekly, $daily);
        $this->assertLessThanOrEqual($daily, $hourly);
    }

    /**
     * getCategoryStatsWithMedianAndTrend() の返り値構造を検証
     * 各行に category, room_count, total_members, median, monthly_trend が含まれること
     */
    public function test_getCategoryStatsWithMedianAndTrend_returns_correct_structure(): void
    {
        $stats = $this->repository->getCategoryStatsWithMedianAndTrend();

        $this->assertNotEmpty($stats, 'カテゴリー統計が空でないこと');

        foreach ($stats as $i => $row) {
            $this->assertArrayHasKey('category', $row, "category キーが存在すること (index: {$i})");
            $this->assertArrayHasKey('room_count', $row, "room_count キーが存在すること (index: {$i})");
            $this->assertArrayHasKey('total_members', $row, "total_members キーが存在すること (index: {$i})");
            $this->assertArrayHasKey('median', $row, "median キーが存在すること (index: {$i})");
            $this->assertArrayHasKey('monthly_trend', $row, "monthly_trend キーが存在すること (index: {$i})");
            $this->assertIsInt($row['category']);
            $this->assertIsInt($row['room_count']);
            $this->assertIsInt($row['total_members']);
            $this->assertIsInt($row['median']);
            $this->assertIsInt($row['monthly_trend']);
            $this->assertGreaterThan(0, $row['room_count']);
            $this->assertGreaterThan(0, $row['total_members']);
            $this->assertGreaterThan(0, $row['median']);
        }
    }

    /**
     * getCategoryStatsWithMedianAndTrend() のルーム数・参加者数がMySQL直接クエリと一致することを検証
     */
    public function test_getCategoryStatsWithMedianAndTrend_matches_basic_stats(): void
    {
        $expected = DB::$pdo->query(
            'SELECT category, COUNT(*) AS room_count, SUM(member) AS total_members
             FROM open_chat WHERE category IS NOT NULL GROUP BY category ORDER BY total_members DESC, category ASC'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $actual = $this->repository->getCategoryStatsWithMedianAndTrend();

        $this->assertSame(count($expected), count($actual), 'カテゴリー数が一致すること');

        foreach ($expected as $i => $row) {
            $this->assertSame((int) $row['category'], $actual[$i]['category'], "カテゴリーIDが一致すること (index: {$i})");
            $this->assertSame((int) $row['room_count'], $actual[$i]['room_count'], "ルーム数が一致すること (category: {$row['category']})");
            $this->assertSame((int) $row['total_members'], $actual[$i]['total_members'], "参加者数が一致すること (category: {$row['category']})");
        }
    }

    /**
     * カテゴリー別ルーム数の合計が総ルーム数を超えないことを検証
     * （カテゴリーがNULLのルームが存在する可能性があるため、合計 <= 総数）
     */
    public function test_getCategoryStatsWithMedianAndTrend_room_count_sum_does_not_exceed_total(): void
    {
        $stats = $this->repository->getCategoryStatsWithMedianAndTrend();
        $totalFromCategories = array_sum(array_column($stats, 'room_count'));
        $totalRooms = $this->repository->getTotalRoomCount();

        $this->assertLessThanOrEqual($totalRooms, $totalFromCategories, 'カテゴリー別合計は総ルーム数以下であること');
    }

    /**
     * getMemberTrend() が整数を返すことを検証
     */
    public function test_getMemberTrend_returns_int(): void
    {
        $daily = $this->repository->getMemberTrend('-1 day');
        $weekly = $this->repository->getMemberTrend('-7 day');
        $monthly = $this->repository->getMemberTrend('-1 month');

        $this->assertIsInt($daily);
        $this->assertIsInt($weekly);
        $this->assertIsInt($monthly);
    }

    /**
     * getDeletedMemberCountSince() の期間別件数が整合していることを検証
     * 1時間 <= 24時間 <= 1週間 <= 1ヶ月 の順でメンバー数が増えること
     */
    public function test_getDeletedMemberCountSince_ordering(): void
    {
        $monthly = $this->repository->getDeletedMemberCountSince('1 month');
        $weekly = $this->repository->getDeletedMemberCountSince('7 day');
        $daily = $this->repository->getDeletedMemberCountSince('24 hour');
        $hourly = $this->repository->getDeletedMemberCountSince('1 hour');

        $this->assertLessThanOrEqual($monthly, $weekly);
        $this->assertLessThanOrEqual($weekly, $daily);
        $this->assertLessThanOrEqual($daily, $hourly);
    }

    /**
     * getDelistedStats() が正しい構造の配列を返すことを検証
     */
    public function test_getDelistedStats_returns_correct_structure(): void
    {
        $result = $this->repository->getDelistedStats('-1 day');

        $this->assertArrayHasKey('rooms', $result);
        $this->assertArrayHasKey('members', $result);
        $this->assertIsInt($result['rooms']);
        $this->assertIsInt($result['members']);
        $this->assertGreaterThanOrEqual(0, $result['rooms']);
        $this->assertGreaterThanOrEqual(0, $result['members']);
    }

    /**
     * getDelistedStats() の期間別件数が整合していることを検証
     * 1日 <= 1週間 <= 1ヶ月 の順で件数が増えること
     */
    public function test_getDelistedStats_ordering(): void
    {
        $daily = $this->repository->getDelistedStats('-1 day');
        $weekly = $this->repository->getDelistedStats('-7 day');
        $monthly = $this->repository->getDelistedStats('-1 month');

        $this->assertLessThanOrEqual($monthly['rooms'], $weekly['rooms']);
        $this->assertLessThanOrEqual($weekly['rooms'], $daily['rooms']);
    }

    /**
     * getMemberDistribution() が7つの人数帯を返すことを検証
     */
    public function test_getMemberDistribution_returns_seven_bands(): void
    {
        $distribution = $this->repository->getMemberDistribution();

        $this->assertNotEmpty($distribution, '分布データが空でないこと');
        $this->assertLessThanOrEqual(7, count($distribution), '最大7バンドであること');

        foreach ($distribution as $band) {
            $this->assertArrayHasKey('band_id', $band);
            $this->assertArrayHasKey('band_label', $band);
            $this->assertArrayHasKey('room_count', $band);
            $this->assertArrayHasKey('total_members', $band);
            $this->assertIsInt($band['band_id']);
            $this->assertIsString($band['band_label']);
            $this->assertIsInt($band['room_count']);
            $this->assertIsInt($band['total_members']);
            $this->assertGreaterThanOrEqual(1, $band['band_id']);
            $this->assertLessThanOrEqual(7, $band['band_id']);
            $this->assertGreaterThan(0, $band['room_count']);
            $this->assertGreaterThan(0, $band['total_members']);
        }
    }

    /**
     * getMemberDistribution() のルーム数合計が総ルーム数と一致することを検証
     */
    public function test_getMemberDistribution_room_count_matches_total(): void
    {
        $distribution = $this->repository->getMemberDistribution();
        $totalFromBands = array_sum(array_column($distribution, 'room_count'));
        $totalRooms = $this->repository->getTotalRoomCount();

        $this->assertSame($totalRooms, $totalFromBands, '分布のルーム数合計が総ルーム数と一致すること');
    }

    /**
     * getMemberDistribution() の参加者数合計が総参加者数と一致することを検証
     */
    public function test_getMemberDistribution_member_count_matches_total(): void
    {
        $distribution = $this->repository->getMemberDistribution();
        $totalFromBands = array_sum(array_column($distribution, 'total_members'));
        $totalMembers = $this->repository->getTotalMemberCount();

        $this->assertSame($totalMembers, $totalFromBands, '分布の参加者数合計が総参加者数と一致すること');
    }

    /**
     * getOverallMedian() が正の整数を返すことを検証
     */
    public function test_getOverallMedian_returns_positive_int(): void
    {
        $median = $this->repository->getOverallMedian();

        $this->assertIsInt($median);
        $this->assertGreaterThan(0, $median, '中央値は正の整数であること');
    }

    /**
     * getOverallMedian() が合理的な範囲内であることを検証
     * 中央値は1以上かつ最大メンバー数以下であること
     */
    public function test_getOverallMedian_within_range(): void
    {
        $median = $this->repository->getOverallMedian();
        $maxMember = $this->queryInt('SELECT MAX(member) FROM open_chat');

        $this->assertGreaterThanOrEqual(1, $median);
        $this->assertLessThanOrEqual($maxMember, $median);
    }
}

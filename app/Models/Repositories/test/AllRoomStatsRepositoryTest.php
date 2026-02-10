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
     * getNewRoomCountSince('1 HOUR') が直近1時間以内に登録されたルーム数と一致することを検証
     */
    public function test_getNewRoomCountSince_hourly_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COUNT(*) FROM open_chat WHERE created_at >= NOW() - INTERVAL 1 HOUR'
        );
        $actual = $this->repository->getNewRoomCountSince('1 HOUR');

        $this->assertSame($expected, $actual);
    }

    /**
     * getNewRoomCountSince('24 HOUR') が直近24時間以内に登録されたルーム数と一致することを検証
     */
    public function test_getNewRoomCountSince_daily_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COUNT(*) FROM open_chat WHERE created_at >= NOW() - INTERVAL 24 HOUR'
        );
        $actual = $this->repository->getNewRoomCountSince('24 HOUR');

        $this->assertSame($expected, $actual);
    }

    /**
     * getNewRoomCountSince('7 DAY') が直近1週間以内に登録されたルーム数と一致することを検証
     */
    public function test_getNewRoomCountSince_weekly_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COUNT(*) FROM open_chat WHERE created_at >= NOW() - INTERVAL 7 DAY'
        );
        $actual = $this->repository->getNewRoomCountSince('7 DAY');

        $this->assertSame($expected, $actual);
    }

    /**
     * getNewRoomCountSince('1 MONTH') が直近1ヶ月以内に登録されたルーム数と一致することを検証
     */
    public function test_getNewRoomCountSince_monthly_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COUNT(*) FROM open_chat WHERE created_at >= NOW() - INTERVAL 1 MONTH'
        );
        $actual = $this->repository->getNewRoomCountSince('1 MONTH');

        $this->assertSame($expected, $actual);
    }

    /**
     * 新規登録ルーム数の期間別件数が整合していることを検証
     * 1時間 <= 24時間 <= 1週間 <= 1ヶ月 <= 全件数 の順で件数が増えること
     */
    public function test_getNewRoomCountSince_ordering(): void
    {
        $total = $this->repository->getTotalRoomCount();
        $monthly = $this->repository->getNewRoomCountSince('1 MONTH');
        $weekly = $this->repository->getNewRoomCountSince('7 DAY');
        $daily = $this->repository->getNewRoomCountSince('24 HOUR');
        $hourly = $this->repository->getNewRoomCountSince('1 HOUR');

        $this->assertLessThanOrEqual($total, $monthly);
        $this->assertLessThanOrEqual($monthly, $weekly);
        $this->assertLessThanOrEqual($weekly, $daily);
        $this->assertLessThanOrEqual($daily, $hourly);
    }

    /**
     * getEarliestDeletedDate() が open_chat_deleted テーブルの MIN(deleted_at) と一致することを検証
     * データが存在しない場合はnullが返る
     */
    public function test_getEarliestDeletedDate_matches_direct_query(): void
    {
        $result = DB::$pdo->query('SELECT MIN(deleted_at) FROM open_chat_deleted')->fetchColumn();
        $expected = $result !== false ? (string) $result : null;
        $actual = $this->repository->getEarliestDeletedDate();

        $this->assertSame($expected, $actual);
    }

    /**
     * getDeletedRoomCount() が open_chat_deleted テーブルの全件数と一致することを検証
     */
    public function test_getDeletedRoomCount_matches_direct_query(): void
    {
        $expected = $this->queryInt('SELECT COUNT(*) FROM open_chat_deleted');
        $actual = $this->repository->getDeletedRoomCount();

        $this->assertSame($expected, $actual);
    }

    /**
     * getDeletedRoomCountSince('1 HOUR') が直近1時間以内に削除されたルーム数と一致することを検証
     */
    public function test_getDeletedRoomCountSince_hourly_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COUNT(*) FROM open_chat_deleted WHERE deleted_at >= NOW() - INTERVAL 1 HOUR'
        );
        $actual = $this->repository->getDeletedRoomCountSince('1 HOUR');

        $this->assertSame($expected, $actual);
    }

    /**
     * getDeletedRoomCountSince('24 HOUR') が直近24時間以内に削除されたルーム数と一致することを検証
     */
    public function test_getDeletedRoomCountSince_daily_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COUNT(*) FROM open_chat_deleted WHERE deleted_at >= NOW() - INTERVAL 24 HOUR'
        );
        $actual = $this->repository->getDeletedRoomCountSince('24 HOUR');

        $this->assertSame($expected, $actual);
    }

    /**
     * getDeletedRoomCountSince('7 DAY') が直近1週間以内に削除されたルーム数と一致することを検証
     */
    public function test_getDeletedRoomCountSince_weekly_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COUNT(*) FROM open_chat_deleted WHERE deleted_at >= NOW() - INTERVAL 7 DAY'
        );
        $actual = $this->repository->getDeletedRoomCountSince('7 DAY');

        $this->assertSame($expected, $actual);
    }

    /**
     * getDeletedRoomCountSince('1 MONTH') が直近1ヶ月以内に削除されたルーム数と一致することを検証
     */
    public function test_getDeletedRoomCountSince_monthly_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COUNT(*) FROM open_chat_deleted WHERE deleted_at >= NOW() - INTERVAL 1 MONTH'
        );
        $actual = $this->repository->getDeletedRoomCountSince('1 MONTH');

        $this->assertSame($expected, $actual);
    }

    /**
     * 削除済みルーム数の期間別件数が整合していることを検証
     * 1時間 <= 24時間 <= 1週間 <= 1ヶ月 <= 全期間 の順で件数が増えること
     */
    public function test_getDeletedRoomCountSince_does_not_exceed_total(): void
    {
        $total = $this->repository->getDeletedRoomCount();
        $monthly = $this->repository->getDeletedRoomCountSince('1 MONTH');
        $hourly = $this->repository->getDeletedRoomCountSince('1 HOUR');
        $daily = $this->repository->getDeletedRoomCountSince('24 HOUR');
        $weekly = $this->repository->getDeletedRoomCountSince('7 DAY');

        $this->assertLessThanOrEqual($total, $monthly);
        $this->assertLessThanOrEqual($monthly, $weekly);
        $this->assertLessThanOrEqual($weekly, $daily);
        $this->assertLessThanOrEqual($daily, $hourly);
    }

    /**
     * getCategoryStats() の返り値が直接SQLの結果と完全一致することを検証
     * カテゴリーID・ルーム数・参加者数を行ごとに照合する
     */
    public function test_getCategoryStats_matches_direct_query(): void
    {
        $expected = DB::$pdo->query(
            'SELECT category, COUNT(*) AS room_count, SUM(member) AS total_members
             FROM open_chat WHERE category IS NOT NULL GROUP BY category ORDER BY room_count DESC, category ASC'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $actual = $this->repository->getCategoryStats();

        $this->assertSame(count($expected), count($actual), 'カテゴリー数が一致すること');

        foreach ($expected as $i => $row) {
            $this->assertSame($row['category'], $actual[$i]['category'], "カテゴリーIDが一致すること (index: {$i})");
            $this->assertSame($row['room_count'], $actual[$i]['room_count'], "ルーム数が一致すること (category: {$row['category']})");
            $this->assertSame($row['total_members'], $actual[$i]['total_members'], "参加者数が一致すること (category: {$row['category']})");
        }
    }

    /**
     * カテゴリー別ルーム数の合計が総ルーム数を超えないことを検証
     * （カテゴリーがNULLのルームが存在する可能性があるため、合計 <= 総数）
     */
    public function test_getCategoryStats_room_count_sum_does_not_exceed_total(): void
    {
        $stats = $this->repository->getCategoryStats();
        $totalFromCategories = array_sum(array_column($stats, 'room_count'));
        $totalRooms = $this->repository->getTotalRoomCount();

        $this->assertLessThanOrEqual($totalRooms, $totalFromCategories, 'カテゴリー別合計は総ルーム数以下であること');
    }

    /**
     * getHourlyMemberIncrease() が statistics_ranking_hour テーブルの
     * diff_member > 0 の合計と一致することを検証
     */
    public function test_getHourlyMemberIncrease_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COALESCE(SUM(diff_member), 0) FROM statistics_ranking_hour WHERE diff_member > 0'
        );
        $actual = $this->repository->getHourlyMemberIncrease();

        $this->assertSame($expected, $actual);
    }

    /**
     * getDailyMemberIncrease() が statistics_ranking_hour24 テーブルの
     * diff_member > 0 の合計と一致することを検証
     */
    public function test_getDailyMemberIncrease_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COALESCE(SUM(diff_member), 0) FROM statistics_ranking_hour24 WHERE diff_member > 0'
        );
        $actual = $this->repository->getDailyMemberIncrease();

        $this->assertSame($expected, $actual);
    }

    /**
     * getWeeklyMemberIncrease() が statistics_ranking_week テーブルの
     * diff_member > 0 の合計と一致することを検証
     */
    public function test_getWeeklyMemberIncrease_matches_direct_query(): void
    {
        $expected = $this->queryInt(
            'SELECT COALESCE(SUM(diff_member), 0) FROM statistics_ranking_week WHERE diff_member > 0'
        );
        $actual = $this->repository->getWeeklyMemberIncrease();

        $this->assertSame($expected, $actual);
    }
}

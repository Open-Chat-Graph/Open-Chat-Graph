<?php

/**
 * RankingPositionHourRepositoryのOHLC関連メソッドのテスト
 *
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Models/RankingPositionDB/Repositories/test/RankingPositionHourRepositoryOhlcTest.php
 *
 * テスト対象メソッド:
 * - getDailyMemberOhlc() - メンバー数のOHLC（始値・高値・安値・終値）集計
 * - getDailyPositionOhlc() - ランキング順位のOHLC集計
 *
 * 前提:
 * - MySQL（RankingPositionDB）上のmember, ranking, risingテーブルに対してテスト
 * - テスト用データは固定日付（2099-12-01）を使用して既存データと競合しない
 */

declare(strict_types=1);

use App\Models\RankingPositionDB\Repositories\RankingPositionHourRepository;
use App\Models\RankingPositionDB\RankingPositionDB;
use App\Services\OpenChat\Enum\RankingType;
use PHPUnit\Framework\TestCase;

class RankingPositionHourRepositoryOhlcTest extends TestCase
{
    private RankingPositionHourRepository $repo;
    private string $testDate = '2099-12-01';

    protected function setUp(): void
    {
        $this->repo = app(RankingPositionHourRepository::class);

        RankingPositionDB::connect();
        $this->cleanupTestData();
        $this->insertTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        RankingPositionDB::$pdo = null;
    }

    private function cleanupTestData(): void
    {
        RankingPositionDB::execute(
            "DELETE FROM member WHERE DATE(time) = :date",
            ['date' => $this->testDate]
        );
        RankingPositionDB::execute(
            "DELETE FROM ranking WHERE DATE(time) = :date",
            ['date' => $this->testDate]
        );
        RankingPositionDB::execute(
            "DELETE FROM rising WHERE DATE(time) = :date",
            ['date' => $this->testDate]
        );
    }

    private function insertTestData(): void
    {
        // memberテーブル: open_chat_id=1001のメンバー数推移（3時間分）
        // 00:30 → 100, 01:30 → 120, 02:30 → 110
        // OHLC期待値: open=100, high=120, low=100, close=110
        $memberData = [
            [1001, 100, "{$this->testDate} 00:30:00"],
            [1001, 120, "{$this->testDate} 01:30:00"],
            [1001, 110, "{$this->testDate} 02:30:00"],
            // open_chat_id=1002: 変動なし 200→200→200
            // OHLC期待値: open=200, high=200, low=200, close=200
            [1002, 200, "{$this->testDate} 00:30:00"],
            [1002, 200, "{$this->testDate} 01:30:00"],
            [1002, 200, "{$this->testDate} 02:30:00"],
        ];

        $stmt = RankingPositionDB::$pdo->prepare(
            "INSERT INTO member (open_chat_id, member, time) VALUES (?, ?, ?)"
        );
        foreach ($memberData as $row) {
            $stmt->execute($row);
        }

        // rankingテーブル: open_chat_id=1001, category=0の順位推移（3時間分）
        // 00:30 → 5位, 01:30 → 3位, 02:30 → 4位
        // OHLC期待値: open=5, high(best)=3, low(worst)=5, close=4
        // 全3スロットに出現 → low_positionはmin_position
        $rankingData = [
            [1001, 5, 0, "{$this->testDate} 00:30:00"],
            [1001, 3, 0, "{$this->testDate} 01:30:00"],
            [1001, 4, 0, "{$this->testDate} 02:30:00"],
        ];

        $stmt = RankingPositionDB::$pdo->prepare(
            "INSERT INTO ranking (open_chat_id, `position`, category, time) VALUES (?, ?, ?, ?)"
        );
        foreach ($rankingData as $row) {
            $stmt->execute($row);
        }

        // risingテーブル: open_chat_id=1001, category=0（2/3スロットのみ出現）
        // 00:30 → 10位, 01:30 → 8位, 02:30 → 出現なし
        // total_slots=3, room_count=2 → low_position=NULL（圏外あり）
        $risingData = [
            [1001, 10, 0, "{$this->testDate} 00:30:00"],
            [1001, 8, 0, "{$this->testDate} 01:30:00"],
            // 02:30はデータなし（圏外）
            // total_slotsカウント用に別のルームのデータを追加
            [9999, 1, 0, "{$this->testDate} 00:30:00"],
            [9999, 1, 0, "{$this->testDate} 01:30:00"],
            [9999, 1, 0, "{$this->testDate} 02:30:00"],
        ];

        $stmt = RankingPositionDB::$pdo->prepare(
            "INSERT INTO rising (open_chat_id, `position`, category, time) VALUES (?, ?, ?, ?)"
        );
        foreach ($risingData as $row) {
            $stmt->execute($row);
        }
    }

    /**
     * @test getDailyMemberOhlc: 時間別メンバー数からOHLCを正しく集計すること
     * - open=最初の時刻の値, high=MAX, low=MIN, close=最後の時刻の値
     * - 変動ありケース（100→120→110）と変動なしケース（200→200→200）を検証
     */
    public function testGetDailyMemberOhlc(): void
    {
        $result = $this->repo->getDailyMemberOhlc(new \DateTime($this->testDate));

        // open_chat_idでインデックス化
        $byId = [];
        foreach ($result as $row) {
            $byId[$row['open_chat_id']] = $row;
        }

        // ID 1001: 100→120→110
        $this->assertArrayHasKey(1001, $byId);
        $this->assertEquals(100, $byId[1001]['open_member'], 'open: 最初の値');
        $this->assertEquals(120, $byId[1001]['high_member'], 'high: 最大値');
        $this->assertEquals(100, $byId[1001]['low_member'], 'low: 最小値');
        $this->assertEquals(110, $byId[1001]['close_member'], 'close: 最後の値');
        $this->assertSame($this->testDate, $byId[1001]['date']);

        // ID 1002: 200→200→200（変動なし）
        $this->assertArrayHasKey(1002, $byId);
        $this->assertEquals(200, $byId[1002]['open_member']);
        $this->assertEquals(200, $byId[1002]['high_member']);
        $this->assertEquals(200, $byId[1002]['low_member']);
        $this->assertEquals(200, $byId[1002]['close_member']);
    }

    /**
     * @test getDailyMemberOhlc: データがない日付は空配列を返すこと
     */
    public function testGetDailyMemberOhlcEmptyDate(): void
    {
        $result = $this->repo->getDailyMemberOhlc(new \DateTime('2099-06-01'));
        $this->assertSame([], $result);
    }

    /**
     * @test getDailyPositionOhlc(Ranking): 順位OHLCが正しく集計されること
     * - open=最初の時刻の順位, high=MAX(position), low=MIN(position), close=最後の時刻の順位
     * - 全スロット出現時は low_position に MIN(position) が入ること
     */
    public function testGetDailyPositionOhlcRanking(): void
    {
        $result = $this->repo->getDailyPositionOhlc(
            RankingType::Ranking,
            new \DateTime($this->testDate)
        );

        $byId = [];
        foreach ($result as $row) {
            $key = $row['open_chat_id'] . '_' . $row['category'];
            $byId[$key] = $row;
        }

        // ID 1001, category 0: 5→3→4（全3スロットに出現）
        $this->assertArrayHasKey('1001_0', $byId);
        $row = $byId['1001_0'];
        $this->assertEquals(5, $row['open_position'], 'open: 最初の順位');
        $this->assertEquals(5, $row['high_position'], 'high: MAX(position)=最も数値が大きい順位');
        $this->assertEquals(3, $row['low_position'], 'low: MIN(position)=最も数値が小さい順位（全スロット出現時）');
        $this->assertEquals(4, $row['close_position'], 'close: 最後の順位');
        $this->assertSame($this->testDate, $row['date']);
    }

    /**
     * @test getDailyPositionOhlc(Rising): 圏外ありの場合 low_position が NULL になること
     * - 全スロット中一部のみ出現（2/3スロット）→ low_position=NULL
     */
    public function testGetDailyPositionOhlcRisingWithOutOfRank(): void
    {
        $result = $this->repo->getDailyPositionOhlc(
            RankingType::Rising,
            new \DateTime($this->testDate)
        );

        $byId = [];
        foreach ($result as $row) {
            $key = $row['open_chat_id'] . '_' . $row['category'];
            $byId[$key] = $row;
        }

        // ID 1001, category 0: 2/3スロットのみ出現 → low_position=NULL
        $this->assertArrayHasKey('1001_0', $byId);
        $row = $byId['1001_0'];
        $this->assertEquals(10, $row['open_position']);
        $this->assertNull($row['low_position'], '圏外がある場合low_positionはNULL');
        $this->assertEquals(8, $row['close_position']);
    }

    /**
     * @test getDailyPositionOhlc: データがない日付は空配列を返すこと
     */
    public function testGetDailyPositionOhlcEmptyDate(): void
    {
        $result = $this->repo->getDailyPositionOhlc(
            RankingType::Ranking,
            new \DateTime('2099-06-01')
        );
        $this->assertSame([], $result);
    }
}

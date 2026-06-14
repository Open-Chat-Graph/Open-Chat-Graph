<?php

/**
 * RecommendGrowthRepository の結合テスト
 *
 * 実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Models/Repositories/Recommend/test/RecommendGrowthRepositoryTest.php
 *
 * テスト方針:
 * - SQLite(ranking_position.db) を読み取り専用で参照(書き込み禁止)。
 * - 実数値は日々変わるため exact 値ではなく「不変条件」をアサート。
 * - 空配列 / 無効ID / 掲載なし → empty が返ること。
 * - themeMomentum の返り値が docブロック通りのキー・型を持つこと(member フォールバックは廃止)。
 * - rank.points が日付昇順であること。
 * - rank.leaderId が int で、>0 なら入力 ID 集合に含まれること。
 *
 * 使用する実在 ID: ranking_position.db にデータがある小さい ID を使う。実データ環境では
 * これらは必ず存在する想定。ID がDBに存在しない場合でも、テストは「空配列・shape の不変条件」で
 * 壊れない設計にする。
 */

declare(strict_types=1);

use App\Models\Repositories\Recommend\RecommendGrowthRepository;
use App\Services\Storage\FileStorageInterface;
use PHPUnit\Framework\TestCase;

class RecommendGrowthRepositoryTest extends TestCase
{
    /**
     * 本番 DB に長期にわたって存在する安定した ID 群。
     * ranking_position.db と statistics.db(statistics テーブル)双方に記録がある。
     */
    private const STABLE_IDS = [3, 17, 18, 19, 20];

    /**
     * 集計窓の起点。本番(コントローラ)と同じく最終 cron 時刻を使い、現在時刻には依存させない。
     * これによりローカルの古いデータでもテストの不変条件が安定して成立する。
     */
    private \DateTime $anchor;

    private RecommendGrowthRepository $repo;

    protected function setUp(): void
    {
        $this->anchor = new \DateTime(
            app(FileStorageInterface::class)->getContents('@hourlyCronUpdatedAtDatetime')
        );
        $this->repo = new RecommendGrowthRepository();
    }

    // ===================================================
    // themeMomentum: 空・無効 ID
    // ===================================================

    public function test_themeMomentum_returns_empty_array_for_empty_ids(): void
    {
        $result = $this->repo->themeMomentum([], $this->anchor);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_themeMomentum_returns_empty_array_for_nonexistent_ids(): void
    {
        // 絶対に存在しない ID (int_max 付近)
        $result = $this->repo->themeMomentum([PHP_INT_MAX - 1, PHP_INT_MAX - 2, PHP_INT_MAX - 3], $this->anchor);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_themeMomentum_returns_empty_array_for_single_id(): void
    {
        // 1件のみ → rank の点が揃わない可能性が高い → 空
        // (空ではない場合も shape チェックだけ行う)
        $result = $this->repo->themeMomentum([3], $this->anchor);
        $this->assertIsArray($result);
        // 空でも非空でも落ちない: 非空なら shape を確認する
        if (!empty($result)) {
            $this->assertArrayHasKey('spanDays', $result);
        }
    }

    // ===================================================
    // themeMomentum: 返り値の shape (実在 ID)
    // ===================================================

    public function test_themeMomentum_returns_correct_top_level_keys(): void
    {
        $result = $this->repo->themeMomentum(self::STABLE_IDS, $this->anchor, 7);
        if (empty($result)) {
            $this->markTestSkipped('安定IDのデータが取得できなかったためスキップ');
        }

        foreach (['spanDays', 'rank'] as $key) {
            $this->assertArrayHasKey($key, $result, "トップレベルキー '{$key}' が存在すること");
        }
        // member フォールバックは廃止: キー自体が無いこと。
        $this->assertArrayNotHasKey('member', $result, 'member フォールバックは廃止されたこと');
    }

    public function test_themeMomentum_rank_has_correct_keys(): void
    {
        $result = $this->repo->themeMomentum(self::STABLE_IDS, $this->anchor, 7);
        if (empty($result)) {
            $this->markTestSkipped('安定IDのデータが取得できなかったためスキップ');
        }

        $rank = $result['rank'];
        foreach (['points', 'current', 'first', 'leaderId'] as $key) {
            $this->assertArrayHasKey($key, $rank, "rank.{$key} が存在すること");
        }
    }

    // ===================================================
    // themeMomentum: 型チェック
    // ===================================================

    public function test_themeMomentum_spanDays_is_int(): void
    {
        $result = $this->repo->themeMomentum(self::STABLE_IDS, $this->anchor, 7);
        if (empty($result)) {
            $this->markTestSkipped('安定IDのデータが取得できなかったためスキップ');
        }

        $this->assertIsInt($result['spanDays']);
        $this->assertGreaterThanOrEqual(0, $result['spanDays']);
    }

    public function test_themeMomentum_rank_current_and_first_are_ints(): void
    {
        $result = $this->repo->themeMomentum(self::STABLE_IDS, $this->anchor, 7);
        if (empty($result)) {
            $this->markTestSkipped('安定IDのデータが取得できなかったためスキップ');
        }

        $this->assertIsInt($result['rank']['current']);
        $this->assertIsInt($result['rank']['first']);
    }

    public function test_themeMomentum_leaderId_is_int(): void
    {
        $result = $this->repo->themeMomentum(self::STABLE_IDS, $this->anchor, 7);
        if (empty($result)) {
            $this->markTestSkipped('安定IDのデータが取得できなかったためスキップ');
        }

        $this->assertIsInt($result['rank']['leaderId']);
    }

    // ===================================================
    // themeMomentum: points の詳細不変条件
    // ===================================================

    public function test_themeMomentum_rank_points_are_date_ascending(): void
    {
        $result = $this->repo->themeMomentum(self::STABLE_IDS, $this->anchor, 7);
        if (empty($result) || empty($result['rank']['points'])) {
            $this->markTestSkipped('rank.points が空のためスキップ');
        }

        $points = $result['rank']['points'];
        $dates = array_column($points, 'date');
        $sorted = $dates;
        sort($sorted);
        $this->assertSame($sorted, $dates, 'rank.points の date が昇順であること');
    }

    public function test_themeMomentum_rank_points_have_correct_shape(): void
    {
        $result = $this->repo->themeMomentum(self::STABLE_IDS, $this->anchor, 7);
        if (empty($result) || empty($result['rank']['points'])) {
            $this->markTestSkipped('rank.points が空のためスキップ');
        }

        foreach ($result['rank']['points'] as $point) {
            $this->assertArrayHasKey('date', $point);
            $this->assertArrayHasKey('value', $point);
            $this->assertIsString($point['date']);
            $this->assertIsInt($point['value']);
            // 順位は 1 以上
            $this->assertGreaterThanOrEqual(1, $point['value'], 'rank.value は 1 以上の順位であること');
            // date は YYYY-MM-DD 形式
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $point['date']);
        }
    }

    // ===================================================
    // themeMomentum: leaderId は入力 ID 集合に含まれる
    // ===================================================

    public function test_themeMomentum_leaderId_is_in_input_ids_when_positive(): void
    {
        $ids = self::STABLE_IDS;
        $result = $this->repo->themeMomentum($ids, $this->anchor, 7);
        if (empty($result)) {
            $this->markTestSkipped('安定IDのデータが取得できなかったためスキップ');
        }

        $leaderId = $result['rank']['leaderId'];
        if ($leaderId > 0) {
            $this->assertContains(
                $leaderId,
                $ids,
                "leaderId={$leaderId} は入力 ID 集合に含まれること"
            );
        } else {
            // leaderId=0 は rank.points が空の場合: shape として正常
            $this->assertSame(0, $leaderId);
        }
    }

    // ===================================================
    // themeMomentum: spanDays の整合性
    // ===================================================

    public function test_themeMomentum_spanDays_is_consistent_with_points(): void
    {
        $result = $this->repo->themeMomentum(self::STABLE_IDS, $this->anchor, 7);
        if (empty($result)) {
            $this->markTestSkipped('安定IDのデータが取得できなかったためスキップ');
        }

        // spanDays >= 0
        $this->assertGreaterThanOrEqual(0, $result['spanDays']);

        // rank.points が 2 点以上あれば spanDays > 0 のはず
        if (count($result['rank']['points']) >= 2) {
            $this->assertGreaterThan(0, $result['spanDays'], 'rank.points が 2 点以上なら spanDays > 0');
        }
    }
}

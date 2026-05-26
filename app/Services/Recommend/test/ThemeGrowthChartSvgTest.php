<?php

/**
 * ThemeGrowthChartSvg の純粋ロジックのテスト
 *
 * 実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Recommend/test/ThemeGrowthChartSvgTest.php
 *
 * テスト方針:
 * - 固定入力による決定的な境界値テスト
 * - MIN_POINTS(=5) 未満なら null / ちょうど5点なら配列を返す
 * - increase = last - first (増=正 / 減=負 / 0 も描画される)
 * - lastX ≈ WIDTH - PAD (= 338 - 2 = 336)
 * - linePath の形式: M で始まり L で連結、座標値が 0〜HEIGHT の範囲内
 * - areaPath が Z で閉じる
 * - 完全フラットな系列でも y が上端(PAD)や下端(HEIGHT)に張り付かず中央付近に来る
 * - 返り値の全キーが存在し、width=338 / height=84 が固定
 */

declare(strict_types=1);

use App\Services\Recommend\ThemeGrowthChartSvg;
use PHPUnit\Framework\TestCase;

class ThemeGrowthChartSvgTest extends TestCase
{
    // ===================================================
    // ヘルパー
    // ===================================================

    /** n 点の等差数列を生成する。start〜end を n 等分 */
    private function makePoints(int $n, int $start, int $end): array
    {
        $points = [];
        for ($i = 0; $i < $n; $i++) {
            $v = (int) round($start + ($end - $start) * $i / max($n - 1, 1));
            $points[] = ['date' => sprintf('2026-01-%02d', $i + 1), 'value' => $v];
        }
        return $points;
    }

    /** 全点が同じ値の系列を生成する */
    private function makeFlatPoints(int $n, int $value): array
    {
        return $this->makePoints($n, $value, $value);
    }

    // ===================================================
    // null 境界値
    // ===================================================

    public function test_returns_null_when_zero_points(): void
    {
        $this->assertNull(ThemeGrowthChartSvg::build([]));
    }

    public function test_returns_null_when_1_point(): void
    {
        $this->assertNull(ThemeGrowthChartSvg::build([
            ['date' => '2026-01-01', 'value' => 100],
        ]));
    }

    public function test_returns_null_when_4_points(): void
    {
        $this->assertNull(ThemeGrowthChartSvg::build($this->makePoints(4, 100, 200)));
    }

    // ===================================================
    // ちょうど MIN_POINTS(5) 点で配列を返す
    // ===================================================

    public function test_returns_array_when_exactly_5_points(): void
    {
        $result = ThemeGrowthChartSvg::build($this->makePoints(5, 100, 200));
        $this->assertIsArray($result);
    }

    public function test_result_has_all_required_keys(): void
    {
        $result = ThemeGrowthChartSvg::build($this->makePoints(5, 100, 200));
        $this->assertNotNull($result);
        foreach (['linePath', 'areaPath', 'lastX', 'lastY', 'first', 'last', 'increase', 'width', 'height'] as $key) {
            $this->assertArrayHasKey($key, $result, "キー '{$key}' が存在すること");
        }
    }

    // ===================================================
    // 固定定数
    // ===================================================

    public function test_width_is_338(): void
    {
        $result = ThemeGrowthChartSvg::build($this->makePoints(5, 100, 200));
        $this->assertNotNull($result);
        $this->assertSame(338, $result['width']);
    }

    public function test_height_is_84(): void
    {
        $result = ThemeGrowthChartSvg::build($this->makePoints(5, 100, 200));
        $this->assertNotNull($result);
        $this->assertSame(84, $result['height']);
    }

    // ===================================================
    // first / last / increase
    // ===================================================

    public function test_first_and_last_values_are_correct(): void
    {
        $points = $this->makePoints(7, 500, 800);
        $result = ThemeGrowthChartSvg::build($points);
        $this->assertNotNull($result);
        $this->assertSame(500, $result['first']);
        $this->assertSame(800, $result['last']);
    }

    public function test_increase_is_positive_for_growing_series(): void
    {
        $result = ThemeGrowthChartSvg::build($this->makePoints(7, 100, 200));
        $this->assertNotNull($result);
        $this->assertSame(100, $result['increase']); // 200 - 100 = 100
    }

    public function test_increase_is_negative_for_declining_series(): void
    {
        $result = ThemeGrowthChartSvg::build($this->makePoints(7, 300, 200));
        $this->assertNotNull($result);
        $this->assertSame(-100, $result['increase']); // 200 - 300 = -100
    }

    public function test_increase_is_zero_for_flat_series(): void
    {
        $result = ThemeGrowthChartSvg::build($this->makeFlatPoints(7, 500));
        $this->assertNotNull($result);
        $this->assertSame(0, $result['increase']);
    }

    public function test_increase_equals_last_minus_first(): void
    {
        $points = $this->makePoints(10, 1234, 5678);
        $result = ThemeGrowthChartSvg::build($points);
        $this->assertNotNull($result);
        $this->assertSame($result['last'] - $result['first'], $result['increase']);
    }

    // ===================================================
    // lastX : 最後の点が右端付近(PAD=2, WIDTH=338 → 336)
    // ===================================================

    public function test_lastX_is_approximately_width_minus_pad(): void
    {
        $result = ThemeGrowthChartSvg::build($this->makePoints(7, 100, 200));
        $this->assertNotNull($result);
        // WIDTH(338) - PAD(2) = 336.0
        $this->assertEqualsWithDelta(336.0, $result['lastX'], 0.5);
    }

    public function test_lastX_is_same_regardless_of_point_count(): void
    {
        $r5  = ThemeGrowthChartSvg::build($this->makePoints(5, 100, 200));
        $r10 = ThemeGrowthChartSvg::build($this->makePoints(10, 100, 200));
        $this->assertNotNull($r5);
        $this->assertNotNull($r10);
        // 点数によらず lastX は 336 に近い
        $this->assertEqualsWithDelta(336.0, $r5['lastX'], 0.5);
        $this->assertEqualsWithDelta(336.0, $r10['lastX'], 0.5);
    }

    // ===================================================
    // lastY の範囲
    // ===================================================

    public function test_lastY_is_within_canvas_bounds(): void
    {
        $result = ThemeGrowthChartSvg::build($this->makePoints(7, 100, 200));
        $this->assertNotNull($result);
        $this->assertGreaterThanOrEqual(0.0, $result['lastY']);
        $this->assertLessThanOrEqual((float) ThemeGrowthChartSvg::HEIGHT, $result['lastY']);
    }

    // ===================================================
    // linePath の形式チェック
    // ===================================================

    public function test_linePath_starts_with_M(): void
    {
        $result = ThemeGrowthChartSvg::build($this->makePoints(5, 100, 200));
        $this->assertNotNull($result);
        $this->assertStringStartsWith('M', $result['linePath']);
    }

    public function test_linePath_contains_L_separators(): void
    {
        $result = ThemeGrowthChartSvg::build($this->makePoints(5, 100, 200));
        $this->assertNotNull($result);
        $this->assertStringContainsString('L', $result['linePath']);
    }

    public function test_linePath_L_count_equals_n_minus_1(): void
    {
        $n = 7;
        $result = ThemeGrowthChartSvg::build($this->makePoints($n, 100, 200));
        $this->assertNotNull($result);
        // n=7 点の場合、M が1つ、L が n-1=6 個
        $this->assertSame($n - 1, substr_count($result['linePath'], 'L'));
    }

    public function test_linePath_coordinates_are_within_canvas(): void
    {
        $result = ThemeGrowthChartSvg::build($this->makePoints(10, 100, 1000));
        $this->assertNotNull($result);
        $h = ThemeGrowthChartSvg::HEIGHT;
        $w = ThemeGrowthChartSvg::WIDTH;

        // "M{x} {y} L{x} {y} ..." パターンから座標を抽出
        preg_match_all('/[ML]([\d.]+) ([\d.]+)/', $result['linePath'], $matches);
        $xs = array_map('floatval', $matches[1]);
        $ys = array_map('floatval', $matches[2]);

        foreach ($xs as $x) {
            $this->assertGreaterThanOrEqual(0.0, $x, "x={$x} が 0 以上であること");
            $this->assertLessThanOrEqual((float) $w, $x, "x={$x} が width={$w} 以下であること");
        }
        foreach ($ys as $y) {
            $this->assertGreaterThanOrEqual(0.0, $y, "y={$y} が 0 以上であること");
            $this->assertLessThanOrEqual((float) $h, $y, "y={$y} が height={$h} 以下であること");
        }
    }

    // ===================================================
    // areaPath
    // ===================================================

    public function test_areaPath_ends_with_Z(): void
    {
        $result = ThemeGrowthChartSvg::build($this->makePoints(5, 100, 200));
        $this->assertNotNull($result);
        $this->assertStringEndsWith('Z', $result['areaPath']);
    }

    public function test_areaPath_starts_with_linePath(): void
    {
        $result = ThemeGrowthChartSvg::build($this->makePoints(5, 100, 200));
        $this->assertNotNull($result);
        $this->assertStringStartsWith($result['linePath'], $result['areaPath']);
    }

    public function test_areaPath_contains_bottom_closing_lines(): void
    {
        // 下端に戻る2本の L が含まれる(底辺を閉じる)
        $result = ThemeGrowthChartSvg::build($this->makePoints(5, 100, 200));
        $this->assertNotNull($result);
        $h = ThemeGrowthChartSvg::HEIGHT;
        $this->assertStringContainsString(" {$h} L", $result['areaPath']);
        $this->assertStringContainsString(" {$h} Z", $result['areaPath']);
    }

    // ===================================================
    // 視覚レンジ: 完全フラット系列でも中央付近に線が来る
    // ===================================================

    public function test_flat_series_y_is_near_vertical_center(): void
    {
        // 全点同一値(1000) → dataRange=0 なので min(visRange) 補正が効き、
        // 線が上端(PAD=2)や下端(HEIGHT-PAD=82)に張り付かず中央(約42)付近に来る。
        $result = ThemeGrowthChartSvg::build($this->makeFlatPoints(7, 1000));
        $this->assertNotNull($result);
        $h = ThemeGrowthChartSvg::HEIGHT;
        $center = $h / 2.0;

        // 全点の y が 中央値 ± height/4 の範囲に収まることを確認(上下端ではない)
        preg_match_all('/[ML]([\d.]+) ([\d.]+)/', $result['linePath'], $matches);
        $ys = array_map('floatval', $matches[2]);

        foreach ($ys as $y) {
            $this->assertGreaterThan(
                $center - $h / 4,
                $y,
                "フラット系列で y={$y} が下端に張り付いていないこと"
            );
            $this->assertLessThan(
                $center + $h / 4,
                $y,
                "フラット系列で y={$y} が上端に張り付いていないこと"
            );
        }
    }

    public function test_small_variation_series_y_is_not_extremes(): void
    {
        // ±2人の微小な揺れ → 全高に増幅されず中央付近に留まる
        $points = [
            ['date' => '2026-01-01', 'value' => 500],
            ['date' => '2026-01-02', 'value' => 502],
            ['date' => '2026-01-03', 'value' => 500],
            ['date' => '2026-01-04', 'value' => 498],
            ['date' => '2026-01-05', 'value' => 500],
        ];
        $result = ThemeGrowthChartSvg::build($points);
        $this->assertNotNull($result);

        preg_match_all('/[ML]([\d.]+) ([\d.]+)/', $result['linePath'], $matches);
        $ys = array_map('floatval', $matches[2]);

        $pad = 2;
        $h = ThemeGrowthChartSvg::HEIGHT;
        foreach ($ys as $y) {
            // 上端(PAD=2)にも下端(HEIGHT-PAD=82)にも張り付かないこと
            $this->assertGreaterThan((float) $pad, $y, "y={$y} が上端(PAD)より大きいこと");
            $this->assertLessThan((float) ($h - $pad), $y, "y={$y} が下端(HEIGHT-PAD)より小さいこと");
        }
    }

    // ===================================================
    // 大量点でも正常動作
    // ===================================================

    public function test_many_points_returns_valid_result(): void
    {
        $result = ThemeGrowthChartSvg::build($this->makePoints(30, 1000, 2000));
        $this->assertNotNull($result);
        $this->assertStringStartsWith('M', $result['linePath']);
        $this->assertStringEndsWith('Z', $result['areaPath']);
        $this->assertEqualsWithDelta(336.0, $result['lastX'], 0.5);
    }

    // ===================================================
    // 減少系列でも描画される (increase < 0)
    // ===================================================

    public function test_declining_series_is_drawn_not_null(): void
    {
        $result = ThemeGrowthChartSvg::build($this->makePoints(7, 500, 100));
        $this->assertNotNull($result);
        $this->assertSame(100, $result['last']);
        $this->assertSame(500, $result['first']);
        $this->assertSame(-400, $result['increase']);
    }
}

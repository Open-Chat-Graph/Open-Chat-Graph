<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Analysis/test/GrowthMathTest.php
 */

declare(strict_types=1);

use App\Services\Analysis\GrowthMath;
use PHPUnit\Framework\TestCase;

class GrowthMathTest extends TestCase
{
    /**
     * 日次系列(x=日index, y=メンバー)から SQLite 集約と同じ総和を作る。
     *
     * @param array<int,int> $members day index => member
     * @return array{n:int, jmin:float, jmax:float, sx:float, sy:float, sxy:float, sxx:float, syy:float, peak:int}
     */
    private function sumsFromSeries(array $members): array
    {
        $n = 0;
        $sx = $sy = $sxy = $sxx = $syy = 0.0;
        $peak = 0;
        $jmin = null;
        $jmax = null;
        $first = null;
        foreach ($members as $x => $m) {
            if ($first === null) {
                $first = $m;
            }
            $n++;
            $sx += $x;
            $sy += $m;
            $sxy += $x * $m;
            $sxx += $x * $x;
            $syy += (float)$m * $m;
            $peak = max($peak, $m);
            $jmin = $jmin === null ? $x : min($jmin, $x);
            $jmax = $jmax === null ? $x : max($jmax, $x);
        }

        return [
            'n' => $n,
            'jmin' => (float)$jmin,
            'jmax' => (float)$jmax,
            'sx' => $sx,
            'sy' => $sy,
            'sxy' => $sxy,
            'sxx' => $sxx,
            'syy' => $syy,
            'peak' => $peak,
            'first' => (int)$first,
        ];
    }

    public function test_safePct(): void
    {
        $this->assertSame(50.0, GrowthMath::safePct(100, 150));
        $this->assertSame(-20.0, GrowthMath::safePct(100, 80));
        $this->assertNull(GrowthMath::safePct(0, 150), 'base<=0 は未定義');
        $this->assertNull(GrowthMath::safePct(-5, 150));
    }

    public function test_periodIncrease(): void
    {
        $up = GrowthMath::periodIncrease(100, 350);
        $this->assertSame(250, $up['diff']);
        $this->assertSame(250.0, $up['pct']);
        $this->assertSame('positive', $up['symbol']);

        $down = GrowthMath::periodIncrease(100, 60);
        $this->assertSame(-40, $down['diff']);
        $this->assertSame('negative', $down['symbol']);

        $flat = GrowthMath::periodIncrease(100, 100);
        $this->assertSame('', $flat['symbol']);
    }

    public function test_regression_perfectLine(): void
    {
        // y = 2x + 1, x=0..9 → slope=2, intercept=1, r2=1
        $members = [];
        for ($x = 0; $x <= 9; $x++) {
            $members[$x] = 2 * $x + 1;
        }
        $s = $this->sumsFromSeries($members);
        $reg = GrowthMath::regression($s);

        $this->assertNotNull($reg);
        $this->assertEqualsWithDelta(2.0, $reg['slope'], 1e-9);
        $this->assertEqualsWithDelta(1.0, $reg['intercept'], 1e-9);
        $this->assertEqualsWithDelta(1.0, $reg['r2'], 1e-9);
    }

    public function test_regression_noVariance_returnsNull(): void
    {
        $this->assertNull(GrowthMath::regression(['n' => 1, 'sx' => 0, 'sy' => 0, 'sxy' => 0, 'sxx' => 0, 'syy' => 0]));
    }

    public function test_steady_steadyGrower(): void
    {
        // 400日かけて 100 → 2095 へ直線的に成長（じわじわ成長の典型）
        $members = [];
        for ($d = 0; $d <= 399; $d++) {
            $members[$d] = 100 + 5 * $d;
        }
        $agg = $this->sumsFromSeries($members);
        $current = $members[399]; // 2095

        $res = GrowthMath::steady($agg, $current, 400);
        $this->assertNotNull($res, '足切りを通過するはず');
        $this->assertGreaterThan(0.0, $res['score']);
        $this->assertEqualsWithDelta(1.0, $res['r2'], 1e-6, '直線なので当てはまり完全');
        $this->assertSame(399, $res['historyDays']);
        $this->assertEqualsWithDelta(5.0, $res['slope'], 1e-6);
        $this->assertNotNull($res['cagr']);
        $this->assertGreaterThan(0.0, $res['cagr']);
    }

    public function test_steady_tooShortHistory_returnsNull(): void
    {
        $members = [];
        for ($d = 0; $d <= 100; $d++) { // 101日 < 365
            $members[$d] = 100 + 5 * $d;
        }
        $agg = $this->sumsFromSeries($members);
        // 101日しかデータが無い部屋を 365日窓 で見ると、窓の6割(219日)に満たず対象外
        $this->assertNull(GrowthMath::steady($agg, $members[100], 365));
    }

    public function test_steady_tinyMember_returnsNull(): void
    {
        $members = [];
        for ($d = 0; $d <= 399; $d++) {
            $members[$d] = 10 + (int)($d / 100); // 常に < 50
        }
        $agg = $this->sumsFromSeries($members);
        $this->assertNull(GrowthMath::steady($agg, $members[399], 400));
    }

    public function test_steady_drawdownPenalizesPumpAndDump(): void
    {
        // 同じ平均規模・履歴でも、ピークから大きく崩落した部屋はスコアが下がる
        $steady = [];
        $dumped = [];
        for ($d = 0; $d <= 399; $d++) {
            $steady[$d] = 100 + 5 * $d;                  // 維持して成長
            $dumped[$d] = $d <= 200 ? 100 + 10 * $d : 2100 - 10 * ($d - 200); // 吹き上げて崩落
        }
        $sSteady = GrowthMath::steady($this->sumsFromSeries($steady), $steady[399], 400);
        $sDumped = GrowthMath::steady($this->sumsFromSeries($dumped), $dumped[399], 400);

        $this->assertNotNull($sSteady);
        if ($sDumped !== null) {
            $this->assertGreaterThan($sDumped['score'], $sSteady['score'], '崩落部屋は減点される');
        } else {
            $this->assertTrue(true); // 崩落部屋が足切りされるのも許容
        }
    }
}

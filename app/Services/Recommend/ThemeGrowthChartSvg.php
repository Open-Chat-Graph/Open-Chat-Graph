<?php

declare(strict_types=1);

namespace App\Services\Recommend;

/**
 * テーマの勢いグラフ(エリアチャート)の SVG ジオメトリと指標を、日次データから組み立てる純粋ロジック。
 *
 * - データ取得は RecommendGrowthRepository、描画(マークアップ)は
 *   Views/components/recommend_growth_chart.php が担う。ここは計算のみで副作用なし。
 * - 入力 points のキーは 'value'(メンバー数・活発部屋数など系列を問わず汎用)。
 * - increase は符号付き(増=正 / 減=負)。下降も誠実に見せるため 0 以下でも描画する。
 * - 描画不可(点が MIN_POINTS 未満)のときだけ null を返し、呼び出し側が出し分ける。
 */
class ThemeGrowthChartSvg
{
    public const WIDTH = 338;
    public const HEIGHT = 84;
    private const PAD = 2;
    private const MIN_POINTS = 5;

    /**
     * @param array{date:string, value:int}[] $points 日付昇順の日次系列(キーは 'value')
     * @return array{
     *   linePath:string, areaPath:string, lastX:float, lastY:float,
     *   first:int, last:int, increase:int, width:int, height:int
     * }|null 描画可能なときのみ配列、不可なら null
     */
    public static function build(array $points): ?array
    {
        if (count($points) < self::MIN_POINTS) {
            return null;
        }

        $vals = array_map(static fn($r): int => (int)$r['value'], $points);
        $n = count($vals);
        $first = $vals[0];
        $last = $vals[$n - 1];
        $increase = $last - $first;

        $min = min($vals);
        $max = max($vals);
        $dataRange = $max - $min;
        // 視覚レンジに下限(現在規模の約12%)を設け、±数室の微小な揺れを全高の「急落」に増幅して
        // "横ばい" 表示と矛盾させない。実際に大きく動く系列は実レンジが勝つので影響しない。
        $range = max($dataRange, 1, (int) round($max * 0.12));
        // 下限が効いた(=ほぼ横ばいの)ときに線が下端へ貼り付かないよう、余白を上下均等にして中央寄せ。
        $base = $min - ($range - $dataRange) / 2;
        $w = self::WIDTH;
        $h = self::HEIGHT;
        $pad = self::PAD;

        $x = static fn(int $i): float => round($pad + $i * ($w - 2 * $pad) / ($n - 1), 1);
        $y = static fn(int $v): float => round($pad + ($h - 2 * $pad) - ($v - $base) / $range * ($h - 2 * $pad), 1);

        $line = '';
        foreach ($vals as $i => $v) {
            $line .= ($i ? 'L' : 'M') . $x($i) . ' ' . $y($v) . ' ';
        }
        $line = rtrim($line);
        $areaPath = $line . ' L' . $x($n - 1) . ' ' . $h . ' L' . $x(0) . ' ' . $h . ' Z';

        return [
            'linePath' => $line,
            'areaPath' => $areaPath,
            'lastX' => $x($n - 1),
            'lastY' => $y($last),
            'first' => $first,
            'last' => $last,
            'increase' => $increase,
            'width' => $w,
            'height' => $h,
        ];
    }
}

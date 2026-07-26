<?php

declare(strict_types=1);

namespace App\Services\OgImage;

/**
 * 通用口ページ（/admin/disable-ads）専用のOGP画像（1200x630 PNG）を生成する。
 *
 * 生成はデプロイ時ではなく手元で行い、PNG をリポジトリにコミットする運用
 * （再生成: `php batch/exec/generate_premium_ogp.php`）。日本語のみ（ページ自体が ja 専用）。
 *
 * 構図: 他のOGP（濃紺グラデ＋青い折れ線＝表口のブランド）とは意図的に別世界にする。
 * 真っ黒に近い地に、下端からサイトのアクセント色（オレンジ）が漏れる——「閉じたドアの
 * 下から光が漏れている」絵にして、隠しページであることを一目で伝える。装飾の折れ線は
 * サイト共通のモチーフだが、色をオレンジ→金にして「表口ではない」ことを示す。
 */
class PremiumOgpImageGenerator extends AbstractCardImageGenerator
{
    /** 下端から漏れる光の高さ（px） */
    private const GLOW_HEIGHT = 300;

    /**
     * 装飾折れ線の正規化座標（x: 0..1, y: 0..1=上端が最大）。右肩上がり。
     * 乱数を使わず固定にして、再生成しても同じ画像になる（差分コミットを汚さない）。
     */
    private const CHART_POINTS = [
        [0.00, 0.16], [0.14, 0.30], [0.28, 0.24], [0.42, 0.46],
        [0.56, 0.40], [0.70, 0.66], [0.85, 0.60], [1.00, 1.00],
    ];

    public function renderPng(): ?string
    {
        if (!$this->canRender()) {
            return null;
        }

        $im = $this->createDarkCanvas();

        $ember = imagecolorallocate($im, 232, 93, 4);
        $gold = imagecolorallocate($im, 246, 196, 83);

        $this->drawDoorLight($im);
        $this->drawDecorativeChart($im, $gold);

        // 文字はドアの脇の小さなサインひとつだけ。説明は og:title / og:description が持つので、
        // 絵は「閉じたドアの下から光が漏れている」ことだけを伝える。
        // GD に字送りの指定は無いので、文字間に空白を挟んで疎な組にする。
        $this->drawLine($im, 'S T A F F   E N T R A N C E', 84, 430, 26, $ember, $this->fontsBold);

        return $this->encodePng($im);
    }

    /** 真っ黒に近い地（上→下でわずかに沈む）。共通の濃紺グラデとは別世界にするため独自に敷く。 */
    private function createDarkCanvas(): \GdImage
    {
        $im = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        $top = [13, 16, 24];
        $bottom = [5, 6, 11];
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $t = $y / self::HEIGHT;
            $col = imagecolorallocate(
                $im,
                (int)round($top[0] + ($bottom[0] - $top[0]) * $t),
                (int)round($top[1] + ($bottom[1] - $top[1]) * $t),
                (int)round($top[2] + ($bottom[2] - $top[2]) * $t),
            );
            imageline($im, 0, $y, self::WIDTH, $y, $col);
        }

        return $im;
    }

    /**
     * 下端から漏れる光。下へ行くほど強く、左右の端へ行くほど弱い（ドアの隙間の見え方）。
     * GD にぼかしは無いので、1px ずつ加算合成して滑らかな減衰を作る。
     */
    private function drawDoorLight(\GdImage $im): void
    {
        $startY = self::HEIGHT - self::GLOW_HEIGHT;
        $cx = self::WIDTH * 0.42; // 中心をやや左に置いて対称すぎる印象を避ける
        $halfW = self::WIDTH * 0.62;

        for ($y = $startY; $y < self::HEIGHT; $y++) {
            // 縦: 下端で 1.0、光の上端で 0。三乗で裾を長く伸ばす
            $v = ($y - $startY) / self::GLOW_HEIGHT;
            $v = $v * $v * $v;

            for ($x = 0; $x < self::WIDTH; $x++) {
                $h = 1 - min(1, abs($x - $cx) / $halfW);
                $a = $v * $h * $h;
                if ($a < 0.004) {
                    continue;
                }

                $rgb = imagecolorat($im, $x, $y);
                // 光の色は下へ行くほどオレンジ→金へ寄せる（熱源が近い感じ）
                $lr = 232 + (246 - 232) * $v;
                $lg = 93 + (196 - 93) * $v;
                $lb = 4 + (83 - 4) * $v;

                imagesetpixel($im, $x, $y, imagecolorallocate(
                    $im,
                    (int)min(255, round((($rgb >> 16) & 0xFF) + ($lr - (($rgb >> 16) & 0xFF)) * $a)),
                    (int)min(255, round((($rgb >> 8) & 0xFF) + ($lg - (($rgb >> 8) & 0xFF)) * $a)),
                    (int)min(255, round((($rgb) & 0xFF) + ($lb - (($rgb) & 0xFF)) * $a)),
                ));
            }
        }
    }

    /** 右下に上昇折れ線を重ねる（塗りは光と喧嘩するので置かず、線とマーカーだけ）。 */
    private function drawDecorativeChart(\GdImage $im, int $lineCol): void
    {
        $left = 640;
        $right = self::WIDTH - 84;
        $topY = 300;
        $bottomY = self::HEIGHT - 150;

        $points = [];
        foreach (self::CHART_POINTS as [$nx, $ny]) {
            $points[] = [
                (int)round($left + ($right - $left) * $nx),
                (int)round($bottomY - ($bottomY - $topY) * $ny),
            ];
        }

        imagesetthickness($im, 5);
        for ($i = 1; $i < count($points); $i++) {
            imageline($im, $points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1], $lineCol);
        }
        imagesetthickness($im, 1);

        [$ex, $ey] = $points[count($points) - 1];
        imagefilledellipse($im, $ex, $ey, 20, 20, $lineCol);
    }
}

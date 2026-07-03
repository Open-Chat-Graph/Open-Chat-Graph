<?php

declare(strict_types=1);

namespace App\Services\OgImage;

/**
 * サイト共通のデフォルトOGP画像（1200x630 PNG）を生成する。
 * トップページ等の og:image と、動的OGカード生成失敗時のフォールバック（public/assets/ogp*.png）に使う。
 *
 * 生成はデプロイ時ではなく手元で行い、PNG をリポジトリにコミットする運用
 * （再生成: `php batch/exec/generate_default_ogp.php`）。文言は t() がロケール（ja/tw/th）で解決する
 * ため、実行時の MimimalCmsConfig::$urlRoot ごとに1枚ずつ書き出す。
 *
 * 構図: /oc カードと同じ濃紺グラデーション地。下部にブランドモチーフの上昇折れ線（装飾・実データ
 * ではない）、中央にサイト名とタグライン、右下にドメイン。
 */
class SiteOgpImageGenerator extends AbstractCardImageGenerator
{
    /**
     * 装飾折れ線の正規化座標（x: 0..1, y: 0..1=下端）。緩やかな上下を挟みつつ右肩上がり。
     * 乱数を使わず固定にして、再生成しても同じ画像になる（差分コミットを汚さない）。
     */
    private const CHART_POINTS = [
        [0.00, 0.30], [0.08, 0.38], [0.16, 0.34], [0.26, 0.48], [0.34, 0.44],
        [0.44, 0.58], [0.52, 0.55], [0.62, 0.68], [0.72, 0.64], [0.82, 0.80],
        [0.92, 0.86], [1.00, 1.00],
    ];

    public function renderPng(): ?string
    {
        if (!$this->canRender()) {
            return null;
        }

        $im = $this->createCanvas();

        $white = imagecolorallocate($im, 245, 247, 252);
        $sub = imagecolorallocate($im, 150, 162, 190);
        $accent = imagecolorallocate($im, 88, 148, 255);

        // --- 下部: ブランドモチーフの上昇折れ線（装飾） ---
        $this->drawDecorativeChart($im, $accent);

        // --- 中央: サイト名（1行・入る最大サイズへ縮小）＋タグライン ---
        $title = t('オプチャグラフ');
        $maxW = self::WIDTH - 2 * 72;
        $size = 68;
        while ($size > 36 && $this->measureLine($title, $size, $this->fontsBold) > $maxW) {
            $size -= 2;
        }
        $tw = $this->measureLine($title, $size, $this->fontsBold);
        $titleTop = 176;
        $this->drawLine($im, $title, intdiv(self::WIDTH - $tw, 2), $titleTop, $size, $white, $this->fontsBold);

        $tagline = t('LINEオープンチャットの人数推移とランキングを毎時間記録');
        $tsize = 28;
        while ($tsize > 20 && $this->measureLine($tagline, $tsize, $this->fontsMedium) > $maxW) {
            $tsize -= 2;
        }
        $tlw = $this->measureLine($tagline, $tsize, $this->fontsMedium);
        $this->drawLine($im, $tagline, intdiv(self::WIDTH - $tlw, 2), $titleTop + (int)round($size * 1.62), $tsize, $sub, $this->fontsMedium);

        // --- フッター右下: ドメイン（oc カードと同位置） ---
        $brand = 'openchat-review.me';
        $bw = $this->measureLine($brand, 26, $this->fontsMedium);
        $this->drawLine($im, $brand, self::WIDTH - $bw - 56, self::HEIGHT - 44, 26, $sub, $this->fontsMedium);

        ob_start();
        imagepng($im, null, 6);
        return ob_get_clean() ?: null;
    }

    /**
     * 下部に塗りつぶし付きの上昇折れ線を描く（/oc カードのスパークラインと同じ描き味）。
     * 先端にだけマーカーを置く。データラベルは無し（特定の数値を示す図ではないため）。
     */
    private function drawDecorativeChart(\GdImage $im, int $lineCol): void
    {
        $left = 72;
        $right = self::WIDTH - 72;
        $topY = 400;
        $bottomY = self::HEIGHT - 96;

        $points = [];
        foreach (self::CHART_POINTS as [$nx, $ny]) {
            $points[] = [
                (int)round($left + ($right - $left) * $nx),
                (int)round($bottomY - ($bottomY - $topY) * $ny),
            ];
        }

        $fill = imagecolorallocate($im, 26, 44, 82);
        $poly = [];
        foreach ($points as [$x, $y]) {
            $poly[] = $x;
            $poly[] = $y;
        }
        $poly[] = $right;
        $poly[] = $bottomY;
        $poly[] = $left;
        $poly[] = $bottomY;
        imagefilledpolygon($im, $poly, $fill);

        imagesetthickness($im, 4);
        for ($i = 1; $i < count($points); $i++) {
            imageline($im, $points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1], $lineCol);
        }
        imagesetthickness($im, 1);

        [$ex, $ey] = $points[count($points) - 1];
        imagefilledellipse($im, $ex, $ey, 16, 16, $lineCol);
    }
}

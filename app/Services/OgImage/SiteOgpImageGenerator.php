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
 * 構図: /oc カードと同じ濃紺グラデーション地。実際のサイトヘッダーと同じロックアップ
 * （緑のロゴアイコン＋サイト名の横並び・左揃え）を上部に置き、その下にタグライン、
 * 下部にブランドモチーフの上昇折れ線（装飾・実データではない）、右下にドメイン。
 */
class SiteOgpImageGenerator extends AbstractCardImageGenerator
{
    /** サイトヘッダーで使っている実物のロゴアイコン（緑のグラフ）。リポ同梱＝環境差なし */
    private const SITE_ICON_FILE = __DIR__ . '/../../../public/assets/icon-192x192.png';

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

        // --- 上部: サイトヘッダーと同じロックアップ（緑ロゴ＋サイト名の横並び・左揃え） ---
        $left = 72;
        $iconSize = 116;
        $iconTop = 148;
        $iconDrawn = $this->drawSiteIcon($im, $left, $iconTop, $iconSize);

        $title = t('オプチャグラフ');
        $textX = $iconDrawn ? $left + $iconSize + 34 : $left;
        $maxW = self::WIDTH - $textX - 72;
        $size = 54;
        while ($size > 30 && $this->measureLine($title, $size, $this->fontsBold) > $maxW) {
            $size -= 2;
        }
        // タイトルの ink をロゴの縦中央に合わせる（GDのサイズはポイント指定＝実インクを実測して置く）
        $bbox = imagettfbbox($size, 0, $this->fontBold, 'あ0A');
        $inkH = -$bbox[7] + $bbox[1];
        $titleTop = $iconTop + intdiv($iconSize - $inkH, 2);
        $this->drawLine($im, $title, $textX, $titleTop, $size, $white, $this->fontsBold);

        // --- タグライン: ロックアップの下・左揃え ---
        $tagline = t('LINEオープンチャットの人数推移とランキングを毎時間記録');
        $tsize = 27;
        while ($tsize > 18 && $this->measureLine($tagline, $tsize, $this->fontsMedium) > self::WIDTH - $left - 72) {
            $tsize -= 2;
        }
        $this->drawLine($im, $tagline, $left, $iconTop + $iconSize + 46, $tsize, $sub, $this->fontsMedium);

        // --- フッター右下: ドメイン（oc カードと同位置） ---
        $brand = 'openchat-review.me';
        $bw = $this->measureLine($brand, 26, $this->fontsMedium);
        $this->drawLine($im, $brand, self::WIDTH - $bw - 56, self::HEIGHT - 44, 26, $sub, $this->fontsMedium);

        return $this->encodePng($im);
    }

    /**
     * サイトロゴ（PNG・角丸と透過はアセット側で済んでいる）をアルファ合成で描く。
     * アセットが読めない環境では何も描かず false（タイトルを左端へ寄せる）。
     */
    private function drawSiteIcon(\GdImage $im, int $x, int $y, int $size): bool
    {
        if (!is_file(self::SITE_ICON_FILE)) {
            return false;
        }
        try {
            $icon = imagecreatefrompng(self::SITE_ICON_FILE);
        } catch (\ErrorException $e) {
            return false; // 壊れたアセット（想定外だが画像全体は止めない）
        }
        if ($icon === false) {
            return false;
        }
        imagealphablending($im, true);
        imagecopyresampled($im, $icon, $x, $y, 0, 0, $size, $size, imagesx($icon), imagesy($icon));
        return true;
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

        $this->drawFilledPolyline($im, $points, $lineCol, $left, $right, $bottomY);

        [$ex, $ey] = $points[count($points) - 1];
        imagefilledellipse($im, $ex, $ey, 16, 16, $lineCol);
    }
}

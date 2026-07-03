<?php

declare(strict_types=1);

namespace App\Services\OgImage;

/**
 * /recommend/{tag}（テーマ別ランキングページ）用の動的OGP画像（1200x630 PNG）を GD で生成する。
 *
 * 構図: 左にタイポグラフィ主体の見出しブロック（eyebrow「LINEオープンチャット」→ ヒーロー
 * 「#タグ」→ アクセントバー付き「人気・活発な部屋ランキング」）、右にランキング上位の部屋
 * アイコンを「順位＝大きさ」で非対称に散らしたクラスタ（1位のみアクセント色のリング）。
 * クラスタ背後には淡い光彩を敷いて奥行きを出す。左上サイト名・右下ドメインは oc カードと共通の
 * 控えめスタイル。文言はロケール依存（ja/tw/th）。
 *
 * テキスト・絵文字・アイコンの描画機構は AbstractCardImageGenerator（共通基盤）に置いてある。
 */
class RecommendCardImageGenerator extends AbstractCardImageGenerator
{
    /** アイコンクラスタに置く部屋数の上限（= CLUSTER レイアウトのスロット数） */
    public const MAX_ROOMS = 5;

    /** ヒーロー（#タグ）の最大/最小フォントサイズ。入らなければ縮小→2行→末尾… の順で退避 */
    private const HERO_MAX_SIZE = 76;
    private const HERO_MIN_SIZE = 40;

    /**
     * アイコンクラスタのレイアウト（順位順）。d=直径、c=中心座標。
     * 大きさの階段で順位を語る（バッジや番号を使わない）。2位は1位に少し重ねて奥行きを出す。
     * @var array{d:int, c:array{0:int,1:int}}[]
     */
    private const CLUSTER = [
        ['d' => 252, 'c' => [895, 262]],
        ['d' => 148, 'c' => [772, 408]],
        ['d' => 118, 'c' => [1028, 432]],
        ['d' => 88,  'c' => [872, 524]],
        ['d' => 64,  'c' => [1108, 152]],
    ];

    /**
     * カード画像を生成し PNG バイト列を返す（ファイルには書かない＝CDN側でキャッシュする方針）。
     * 生成できない環境（GD/FreeType/フォント無し）では null を返す（呼び出し側でデフォルト画像に退避）。
     *
     * @param string $tag テーマ名（タグ）
     * @param array{iconUrl:?string}[] $rooms ランキング上位の部屋（表示順）。先頭 MAX_ROOMS 件を描く
     */
    public function renderPng(string $tag, array $rooms): ?string
    {
        if (!$this->canRender()) {
            return null;
        }

        // --- 背景: 上下方向の濃紺グラデーション ---
        $im = $this->createCanvas();

        $white = imagecolorallocate($im, 245, 247, 252);
        $sub = imagecolorallocate($im, 150, 162, 190);
        $accent = imagecolorallocate($im, 88, 148, 255);

        // --- 右: アイコンクラスタ（背後に淡い光彩 → 順位＝大きさの円を非対称に配置） ---
        $this->drawGlow($im, 910, 300, 330);
        $this->drawIconCluster($im, array_slice($rooms, 0, self::MAX_ROOMS), $accent);

        // --- 左上: サイト名（oc カードと同じ控えめスタイル） ---
        $this->drawLine($im, t('オプチャグラフ'), 72, 28, 26, $sub, $this->fontsMedium);

        // --- 左: 見出しブロック（eyebrow → ヒーロー → アクセントバー付きラベル） ---
        $left = 72;
        $zoneW = 548; // クラスタ最左の円に食い込まない幅

        $this->drawLine($im, t('LINEオープンチャット'), $left, 174, 28, $sub, $this->fontsMedium);

        $heroBottom = $this->drawHero($im, $tag, $left, 230, $zoneW, $white, $accent);

        $labelTop = $heroBottom + 44;
        // ラベル先頭のアクセントバー（テキストの ink 高に合わせた縦棒）
        imagefilledrectangle($im, $left, $labelTop + 4, $left + 5, $labelTop + 36, $accent);
        $this->drawLine($im, t('人気・活発な部屋ランキング'), $left + 24, $labelTop, 31, $white, $this->fontsBold);

        // --- フッター右下: ドメイン（oc カードと同位置） ---
        $brand = 'openchat-review.me';
        $bw = $this->measureLine($brand, 26, $this->fontsMedium);
        $this->drawLine($im, $brand, self::WIDTH - $bw - 56, self::HEIGHT - 44, 26, $sub, $this->fontsMedium);

        // --- PNG をバイト列で返す（ファイルには書かない） ---
        ob_start();
        imagepng($im, null, 6);
        return ob_get_clean() ?: null;
    }

    /**
     * ヒーロー「#タグ」を描く。「#」はアクセント色・タグは白の2トーン（ハッシュタグ＝テーマの記号で、
     * ロケールを問わず通じる）。1行で入る最大サイズ(76→40)へ自動縮小し、下限でも入らない長いタグは
     * 「#」を1行目に添えたまま drawTitle で2行に折り返す（それでも溢れたら末尾…）。
     *
     * @return int ヒーロー最終行の ink 下端のおおよその Y 座標
     */
    private function drawHero(\GdImage $im, string $tag, int $x, int $topY, int $maxWidth, int $white, int $accent): int
    {
        $gap = 10; // 「#」とタグの間の空き

        $size = self::HERO_MAX_SIZE;
        while ($size > self::HERO_MIN_SIZE) {
            $w = $this->measureLine('#', $size, $this->fontsBold) + $gap
                + $this->measureLine($tag, $size, $this->fontsBold);
            if ($w <= $maxWidth) {
                break;
            }
            $size -= 2;
        }

        $hashW = $this->drawLine($im, '#', $x, $topY, $size, $accent, $this->fontsBold);
        $textX = $x + $hashW + $gap;

        $w = $this->measureLine($tag, $size, $this->fontsBold);
        if ($w <= $maxWidth - $hashW - $gap) {
            $this->drawLine($im, $tag, $textX, $topY, $size, $white, $this->fontsBold);
            return $topY + (int)round($size * 1.12);
        }

        // 下限サイズでも1行に入らない長いタグ: 「#」のぶら下げインデントで2行に折り返す
        return $this->drawTitle($im, $tag, $textX, $topY, $maxWidth - $hashW - $gap, $size, 2, $white);
    }

    /**
     * ランキング上位の部屋アイコンを CLUSTER のスロットへ順位順に描く。
     * 円は薄いリングで背景と分離し、1位だけアクセント色のリングで「勝者」を示す。
     *
     * @param array{iconUrl:?string}[] $rooms 表示順（=順位順）
     */
    private function drawIconCluster(\GdImage $im, array $rooms, int $accent): void
    {
        // アイコン取得失敗時のプレースホルダ円は背景に沈む控えめな色（目立つ円の羅列を避ける）
        $placeholder = imagecolorallocate($im, 40, 52, 84);
        $ring = imagecolorallocate($im, 58, 74, 112);

        foreach ($rooms as $i => $room) {
            $slot = self::CLUSTER[$i] ?? null;
            if ($slot === null) {
                break;
            }
            [$cx, $cy] = $slot['c'];
            $d = $slot['d'];
            $ringW = $i === 0 ? 5 : 3;
            imagefilledellipse($im, $cx, $cy, $d + $ringW * 2, $d + $ringW * 2, $i === 0 ? $accent : $ring);
            $this->drawIcon($im, $room['iconUrl'], $cx - intdiv($d, 2), $cy - intdiv($d, 2), $d, $placeholder);
        }
    }

    /**
     * アイコンクラスタの背後に淡い光彩を敷く（半透明の同心円を重ねて中心ほど明るく）。
     * フラットなグラデ背景に奥行きを足すための控えめな環境光。
     */
    private function drawGlow(\GdImage $im, int $cx, int $cy, int $r): void
    {
        imagealphablending($im, true);
        $steps = 18;
        $col = imagecolorallocatealpha($im, 64, 96, 170, 125);
        for ($i = $steps; $i >= 1; $i--) {
            $d = (int)($r * 2 * $i / $steps);
            imagefilledellipse($im, $cx, $cy, $d, $d, $col);
        }
    }
}

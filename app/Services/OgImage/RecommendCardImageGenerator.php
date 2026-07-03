<?php

declare(strict_types=1);

namespace App\Services\OgImage;

/**
 * /recommend/{tag}（テーマ別ランキングページ）用の動的OGP画像（1200x630 PNG）を GD で生成する。
 *
 * レイアウト: 左上にサイト名・右上に「1時間ごとに更新」。中央上にタグ見出し（1行・自動縮小）と
 * アクセント色のサブタイトル「人気・活発な部屋ランキング」、その下に掲載部屋数とメンバー合計。
 * 下段にランキング上位の部屋アイコン（円形・順位バッジ付き）を最大5件並べ、各アイコンの下に
 * メンバー数を添える。フッター右下にドメイン。文言はロケール依存（ja/tw/th）。
 *
 * テキスト・絵文字・アイコンの描画機構は AbstractCardImageGenerator（共通基盤）に置いてある。
 */
class RecommendCardImageGenerator extends AbstractCardImageGenerator
{
    /** アイコン列に並べる部屋数の上限 */
    public const MAX_ROOMS = 5;

    /**
     * カード画像を生成し PNG バイト列を返す（ファイルには書かない＝CDN側でキャッシュする方針）。
     * 生成できない環境（GD/FreeType/フォント無し）では null を返す（呼び出し側でデフォルト画像に退避）。
     *
     * @param string $tag         テーマ名（タグ）
     * @param int    $roomCount   掲載部屋数（ページの表示件数と同じ基準）
     * @param int    $totalMember 掲載部屋の合計メンバー数
     * @param array{member:int, iconUrl:?string}[] $rooms ランキング上位の部屋（表示順）。先頭 MAX_ROOMS 件を描く
     */
    public function renderPng(string $tag, int $roomCount, int $totalMember, array $rooms): ?string
    {
        if (!$this->canRender()) {
            return null;
        }

        // --- 背景: 上下方向の濃紺グラデーション ---
        $im = $this->createCanvas();

        $white = imagecolorallocate($im, 245, 247, 252);
        $sub = imagecolorallocate($im, 150, 162, 190);
        $accent = imagecolorallocate($im, 88, 148, 255);

        // --- 左上: サイト名 ／ 右上: 更新頻度（oc カードと同じ控えめスタイル） ---
        $this->drawLine($im, t('オプチャグラフ'), 72, 28, 26, $sub, $this->fontsMedium);
        $updated = t('1時間ごとに更新');
        $uw = $this->measureLine($updated, 24, $this->fontsMedium);
        $this->drawLine($im, $updated, self::WIDTH - $uw - 72, 30, 24, $sub, $this->fontsMedium);

        $left = 72;
        $rightEdge = self::WIDTH - 72;

        // --- タグ見出し（1行・52pxから自動縮小。超長タグのみ末尾…） ---
        $title = sprintfT('「%s」のオープンチャット', $tag);
        $titleBottom = $this->drawTitle($im, $title, $left, 104, $rightEdge - $left, 52, 1, $white);

        // --- サブタイトル: ランキングであることをアクセント色で明示 ---
        $subtitleTop = $titleBottom + 22;
        $this->drawLine($im, t('人気・活発な部屋ランキング'), $left, $subtitleTop, 32, $accent, $this->fontsBold);

        // --- 統計行: 掲載部屋数・メンバー合計（このテーマの規模感を1行で伝える） ---
        $stats = sprintfT('%1$s部屋を掲載・メンバー合計 %2$s人', number_format($roomCount), number_format($totalMember));
        $this->drawLine($im, $stats, $left, $subtitleTop + 58, 28, $sub, $this->fontsMedium);

        // --- ランキング上位の部屋アイコン列（円形＋順位バッジ、下にメンバー数） ---
        $this->drawRoomRow($im, array_slice($rooms, 0, self::MAX_ROOMS), $left, $rightEdge, $white, $sub, $accent);

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
     * ランキング上位の部屋を等間隔スロットに並べる。各スロット: 円形アイコン＋左上に順位バッジ、
     * 直下にメンバー数。部屋が無ければ何も描かない（見出しだけのカードになる）。
     *
     * @param array{member:int, iconUrl:?string}[] $rooms 表示順（=順位順）
     */
    private function drawRoomRow(\GdImage $im, array $rooms, int $left, int $rightEdge, int $white, int $sub, int $accent): void
    {
        $n = count($rooms);
        if ($n === 0) {
            return;
        }

        $iconSize = 136;
        $iconY = 392;
        $slotW = intdiv($rightEdge - $left, $n);
        // アイコン取得失敗時のプレースホルダ円は背景に沈む控えめな色（アクセント円の羅列を避ける）
        $placeholder = imagecolorallocate($im, 44, 58, 92);

        foreach ($rooms as $i => $room) {
            $cx = $left + $slotW * $i + intdiv($slotW, 2); // スロット中心
            $iconX = $cx - intdiv($iconSize, 2);
            $this->drawIcon($im, $room['iconUrl'], $iconX, $iconY, $iconSize, $placeholder);

            // 順位バッジ（アイコン左上に重ねる小円＋白抜き数字）
            $badgeD = 44;
            $badgeCx = $iconX + 6;
            $badgeCy = $iconY + 6;
            imagefilledellipse($im, $badgeCx, $badgeCy, $badgeD, $badgeD, $accent);
            $rank = (string)($i + 1);
            $rw = $this->measureLine($rank, 24, $this->fontsBold);
            $this->drawLine($im, $rank, $badgeCx - intdiv($rw, 2), $badgeCy - 17, 24, $white, $this->fontsBold);

            // メンバー数（アイコン直下・中央揃え）
            $label = number_format((int)$room['member']) . t('人');
            $lw = $this->measureLine($label, 24, $this->fontsMedium);
            $this->drawLine($im, $label, $cx - intdiv($lw, 2), $iconY + $iconSize + 18, 24, $sub, $this->fontsMedium);
        }
    }
}

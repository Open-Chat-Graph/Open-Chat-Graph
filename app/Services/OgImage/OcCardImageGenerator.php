<?php

declare(strict_types=1);

namespace App\Services\OgImage;

/**
 * ルーム個別ページ用の動的OGP画像（1200x630 PNG）を GD で生成する。
 *
 * レイアウト: 左に部屋アイコン、その右に「ヘッダー(メンバー数＋7日増減) 1行」＋「部屋名 最大3行
 * （多言語・比例フォント・はみ出しは … で省略）」。下段に30日メンバー数スパークライン（両端に開始/
 * 終了日）。フッター左にサイト名、右にドメイン。ヘッダー文言・サイト名は表示ロケール(ja/tw/th)で翻訳。
 *
 * 多言語テキスト描画・円形アイコン・折り返しタイトル等の共通処理は GdTextRenderer（基底クラス）に
 * 集約されている（TikTok 用動画スライドと共有）。このクラスは OGP カード固有のレイアウトだけを持つ。
 */
class OcCardImageGenerator extends GdTextRenderer
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;

    /**
     * カード画像を生成し PNG バイト列を返す（ファイルには書かない＝CDN側でキャッシュする方針）。
     * 生成できない環境（GD/FreeType/フォント無し）では null を返す（呼び出し側でデフォルト画像に退避）。
     *
     * @param string     $name     部屋名（多言語可・最大3行に折り返し、はみ出しは省略）
     * @param int        $member   現在メンバー数
     * @param int|null   $diffWeek 直近7日のメンバー増減（不明は null＝非表示）
     * @param int[]      $series   スパークライン用のメンバー数系列（日付昇順・30日程度、空なら非表示）
     * @param ?string    $iconUrl  部屋アイコンURL（取得失敗・null はプレースホルダ円）
     * @param string[]   $dates    $series と同じ並びの日付(Y-m-d)。グラフ両端に開始/終了日を薄く入れる
     */
    public function renderPng(string $name, int $member, ?int $diffWeek, array $series, ?string $iconUrl, array $dates = []): ?string
    {
        // 描画能力を事前に control-flow で判定する（例外に頼らない）
        if (!$this->canRenderText()) {
            return null;
        }

        $im = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        // --- 背景: 上下方向の濃紺グラデーション ---
        $top = [24, 32, 54];
        $bottom = [10, 14, 26];
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $t = $y / self::HEIGHT;
            $col = imagecolorallocate(
                $im,
                (int)($top[0] + ($bottom[0] - $top[0]) * $t),
                (int)($top[1] + ($bottom[1] - $top[1]) * $t),
                (int)($top[2] + ($bottom[2] - $top[2]) * $t),
            );
            imageline($im, 0, $y, self::WIDTH, $y, $col);
        }

        $white = imagecolorallocate($im, 245, 247, 252);
        $sub = imagecolorallocate($im, 150, 162, 190);
        $green = imagecolorallocate($im, 76, 217, 123);
        $red = imagecolorallocate($im, 240, 98, 98);
        $accent = imagecolorallocate($im, 88, 148, 255);

        // --- スパークライン（下段・先に描いて他要素を上に重ねる。両端に開始/終了日を薄く） ---
        $this->drawSparkline($im, $series, $dates, $accent, $sub);

        // --- 部屋アイコン（左上・円形クロップ） ---
        $iconSize = 190;
        $iconX = 72;
        $iconY = 66;
        $this->drawIcon($im, $iconUrl, $iconX, $iconY, $iconSize, $accent);

        $rightX = $iconX + $iconSize + 40; // = 302
        $rightEdge = self::WIDTH - 72;      // = 1128

        // --- ヘッダー1行目: メンバー数（muted）＋ 7日増減（緑/赤）。上端をアイコン上端に合わせる。
        //     文言はロケール依存（ja/tw/th）。sprintfT/t が urlRoot を見て翻訳を返す ---
        $headHead = sprintfT('メンバー %s人', number_format($member));
        $headY = $iconY + 2;
        $advance = $this->drawLine($im, $headHead, $rightX, $headY, 28, $sub, $this->fontsMedium);
        if ($diffWeek !== null) {
            $isUp = $diffWeek >= 0;
            $growth = ($isUp ? '▲ +' : '▼ ') . number_format($diffWeek) . ' / ' . t('7日');
            $this->drawLine($im, $growth, $rightX + $advance + 22, $headY, 28, $isUp ? $green : $red, $this->fontsBold);
        }

        // --- タイトル: 部屋名（最大3行。38pxで収まらなければ自動縮小。テキスト=Noto CJK/Thai / 絵文字=カラー / 記号=モノクロ） ---
        // ヘッダー(28px・ink下端≈headY+34)の下、少し間隔を空けて開始。$topY はタイトル1行目の ink 上端。
        $this->drawTitle($im, $name, $rightX, $headY + 58, $rightEdge - $rightX, 38, 3, $white);

        // --- フッター: 左にサイト名（ロケール依存）、右にドメイン（左下/右下） ---
        $siteName = t('オプチャグラフ');
        $this->drawLine($im, $siteName, 72, self::HEIGHT - 44, 26, $sub, $this->fontsMedium);
        $brand = 'openchat-review.me';
        $bw = $this->measureLine($brand, 26, $this->fontsMedium);
        $this->drawLine($im, $brand, self::WIDTH - $bw - 56, self::HEIGHT - 44, 26, $sub, $this->fontsMedium);

        // --- PNG をバイト列で返す（ファイルには書かない） ---
        ob_start();
        imagepng($im, null, 6);
        return ob_get_clean() ?: null;
    }

    /**
     * 30日メンバー数スパークライン（塗りつぶし付き折れ線）を下部に描画する。系列が2点未満なら描かない。
     * $dates（$series と同じ並び）があれば、グラフ両端の下に開始/終了日(M/D)を薄く添える
     *（OGPはCDNキャッシュで固定されるので「いつの範囲か」が分かるように）。
     *
     * @param int[] $series
     * @param string[] $dates
     */
    private function drawSparkline(\GdImage $im, array $series, array $dates, int $lineCol, int $labelCol): void
    {
        // member が null の点は落とすが、対応する日付も一緒に落として整合を保つ
        $pairs = [];
        foreach (array_values($series) as $i => $v) {
            if ($v !== null) {
                $pairs[] = [$v, $dates[$i] ?? null];
            }
        }
        $n = count($pairs);
        if ($n < 2) {
            return;
        }
        $values = array_column($pairs, 0);

        $left = 72;
        $right = self::WIDTH - 72;
        $topY = 400;
        $bottomY = self::HEIGHT - 96;

        $min = min($values);
        $max = max($values);
        $range = max(1, $max - $min);
        $pad = (int)($range * 0.15);
        $min -= $pad;
        $range = max(1, $max - $min);

        $points = [];
        for ($i = 0; $i < $n; $i++) {
            $x = (int)($left + ($right - $left) * $i / ($n - 1));
            $y = (int)($bottomY - ($bottomY - $topY) * ($values[$i] - $min) / $range);
            $points[] = [$x, $y];
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
        for ($i = 1; $i < $n; $i++) {
            imageline($im, $points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1], $lineCol);
        }
        imagesetthickness($im, 1);

        [$ex, $ey] = $points[$n - 1];
        imagefilledellipse($im, $ex, $ey, 16, 16, $lineCol);

        // 両端の日付(M/D)を控えめに（開始=左下、終了=右下）
        $startLabel = $this->shortDate($pairs[0][1]);
        $endLabel = $this->shortDate($pairs[$n - 1][1]);
        $ly = $bottomY + 10;
        if ($startLabel !== '') {
            $this->drawLine($im, $startLabel, $left, $ly, 22, $labelCol, $this->fontsMedium);
        }
        if ($endLabel !== '') {
            $ew = $this->measureLine($endLabel, 22, $this->fontsMedium);
            $this->drawLine($im, $endLabel, $right - $ew, $ly, 22, $labelCol, $this->fontsMedium);
        }
    }
}

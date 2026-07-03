<?php

declare(strict_types=1);

namespace App\Services\OgImage;

/**
 * ルーム個別ページ用の動的OGP画像（1200x630 PNG）を GD で生成する。
 *
 * レイアウト: 左に部屋アイコン、その右に「ヘッダー(メンバー数＋1週間増減) 1行」＋「部屋名 最大3行
 * （多言語・比例フォント・はみ出しは … で省略）」。下段に1週間（先週の同じ曜日→今日）のメンバー数
 * スパークライン（両端に開始/終了日）。フッター左にサイト名、右にドメイン。ヘッダー文言・サイト名は
 * 表示ロケール(ja/tw/th)で翻訳。
 *
 * テキスト・絵文字・アイコンの描画機構は AbstractCardImageGenerator（共通基盤）に置いてある。
 */
class OcCardImageGenerator extends AbstractCardImageGenerator
{
    /**
     * カード画像を生成し PNG バイト列を返す（ファイルには書かない＝CDN側でキャッシュする方針）。
     * 生成できない環境（GD/FreeType/フォント無し）では null を返す（呼び出し側でデフォルト画像に退避）。
     *
     * @param string     $name     部屋名（多言語可・最大3行に折り返し、はみ出しは省略）
     * @param int        $member   現在メンバー数
     * @param int|null   $diffWeek 直近1週間（先週の同じ曜日比）のメンバー増減（不明は null＝非表示）
     * @param int[]      $series   スパークライン用のメンバー数系列（日付昇順・1週間=8点程度、空なら非表示）
     * @param ?string    $iconUrl  部屋アイコンURL（取得失敗・null はプレースホルダ円）
     * @param string[]   $dates    $series と同じ並びの日付(Y-m-d)。グラフ両端に開始/終了日を薄く入れる
     */
    public function renderPng(string $name, int $member, ?int $diffWeek, array $series, ?string $iconUrl, array $dates = []): ?string
    {
        if (!$this->canRender()) {
            return null;
        }

        // --- 背景: 上下方向の濃紺グラデーション ---
        $im = $this->createCanvas();

        $white = imagecolorallocate($im, 245, 247, 252);
        $sub = imagecolorallocate($im, 150, 162, 190);
        $green = imagecolorallocate($im, 76, 217, 123);
        $red = imagecolorallocate($im, 240, 98, 98);
        $accent = imagecolorallocate($im, 88, 148, 255);

        // --- スパークライン（下段・先に描いて他要素を上に重ねる。最新ポイントに人数＋日付ラベル） ---
        $this->drawSparkline($im, $series, $dates, $member, $accent, $sub);

        // --- 部屋アイコン（左上・円形クロップ） ---
        // 左上に置いたサイト名(y=28)の分、アイコン/ヘッダー/タイトルの上端を少し下げて余白を作る
        //（headY・タイトル開始は iconY 起点で連動して下がる）。
        $iconSize = 190;
        $iconX = 72;
        $iconY = 96;
        $this->drawIcon($im, $iconUrl, $iconX, $iconY, $iconSize, $accent);

        $rightX = $iconX + $iconSize + 40; // = 302
        $rightEdge = self::WIDTH - 72;      // = 1128

        // --- ヘッダー1行目: メンバー数（muted）＋ 1週間増減（緑/赤）。上端をアイコン上端に合わせる。
        //     文言はロケール依存（ja/tw/th）。sprintfT/t が urlRoot を見て翻訳を返す ---
        $headHead = sprintfT('メンバー %s人', number_format($member));
        $headY = $iconY + 2;
        $advance = $this->drawLine($im, $headHead, $rightX, $headY, 28, $sub, $this->fontsMedium);
        if ($diffWeek !== null) {
            $isUp = $diffWeek >= 0;
            // ページの統計表示と同じ「1週間」表記（グラフのスパンも同じ観測窓＝矛盾を出さない）
            $growth = ($isUp ? '▲ +' : '▼ ') . number_format($diffWeek) . ' / ' . t('1週間');
            $this->drawLine($im, $growth, $rightX + $advance + 22, $headY, 28, $isUp ? $green : $red, $this->fontsBold);
        }

        // --- タイトル: 部屋名（最大3行。38pxで収まらなければ自動縮小。テキスト=Noto CJK/Thai / 絵文字=カラー / 記号=モノクロ） ---
        // ヘッダー(28px・ink下端≈headY+34)の下、少し間隔を空けて開始。$topY はタイトル1行目の ink 上端。
        $this->drawTitle($im, $name, $rightX, $headY + 58, $rightEdge - $rightX, 38, 3, $white);

        // --- サイト名: 左上。X は下部にキャプション帯を重ねるので、ブランド名は隠れない上端へ。 ---
        $siteName = t('オプチャグラフ');
        $this->drawLine($im, $siteName, 72, 28, 26, $sub, $this->fontsMedium);

        // --- フッター右下: ドメイン（原状のまま） ---
        $brand = 'openchat-review.me';
        $bw = $this->measureLine($brand, 26, $this->fontsMedium);
        $this->drawLine($im, $brand, self::WIDTH - $bw - 56, self::HEIGHT - 44, 26, $sub, $this->fontsMedium);

        // --- PNG をバイト列で返す（ファイルには書かない） ---
        ob_start();
        imagepng($im, null, 6);
        return ob_get_clean() ?: null;
    }

    /** 1:1サムネイルの一辺（px）。meta name="thumbnail"（検索用）向けなので OGP より小さくてよい */
    private const THUMB_SIZE = 640;

    /**
     * 検索用 1:1 サムネイル（640x640 PNG）を生成する（meta name="thumbnail" 用）。
     * 検索結果では小さく表示されるため、部屋アイコンを全面に敷き、下部の暗幕（スクリム）に
     * 部屋名（最大2行）とサイト名だけを重ねる。アイコンが無い部屋は濃紺グラデーションが背景になる。
     * 生成できない環境では null（呼び出し側でデフォルト画像に退避）。
     */
    public function renderThumbPng(string $name, ?string $iconUrl): ?string
    {
        if (!$this->canRender()) {
            return null;
        }

        $s = self::THUMB_SIZE;
        $im = $this->createCanvas($s, $s);

        $icon = $iconUrl ? $this->loadIcon($iconUrl) : null;
        if ($icon) {
            imagecopyresampled($im, $icon, 0, 0, 0, 0, $s, $s, imagesx($icon), imagesy($icon));
        }

        // 下部スクリム: 透明→濃紺のグラデーションを重ね、アイコンの柄に関わらず部屋名を読めるようにする
        imagealphablending($im, true);
        $scrimTop = $s - 240;
        for ($y = $scrimTop; $y < $s; $y++) {
            $t = ($y - $scrimTop) / ($s - $scrimTop);
            $alpha = 127 - (int)round(115 * $t);
            imageline($im, 0, $y, $s, $y, imagecolorallocatealpha($im, 10, 14, 26, $alpha));
        }

        $white = imagecolorallocate($im, 245, 247, 252);
        $sub = imagecolorallocate($im, 170, 182, 210);
        $this->drawTitle($im, $name, 36, $s - 186, $s - 72, 34, 2, $white);
        $this->drawLine($im, t('オプチャグラフ'), 36, $s - 52, 20, $sub, $this->fontsMedium);

        ob_start();
        imagepng($im, null, 6);
        return ob_get_clean() ?: null;
    }

    /**
     * 1週間のメンバー数スパークライン（塗りつぶし付き折れ線）を下部に描画する。系列が2点未満なら描かない。
     * 最新ポイント（右端の点）の上に「人数＋日付」をデータラベルとして重ねる（OGPはCDNキャッシュで
     * 固定されるので「いつ時点の何人か」が点の位置で分かるように）。
     *
     * @param int[]    $series
     * @param string[] $dates
     * @param int      $member 最新人数（データラベルに出す）
     */
    private function drawSparkline(\GdImage $im, array $series, array $dates, int $member, int $lineCol, int $labelCol): void
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

        // --- 両端の点にデータラベル（上段=日付 M/D・下段=人数）。始点と終点の両方を出して
        //     「いつ何人 → いつ何人」の増減が読めるようにする。文字は元の日付ラベルと同じ
        //     控えめスタイル(fontsMedium・muted)。点の“上”に積むので X の下部帯にも隠れにくい。 ---
        $this->drawPointLabel($im, $points[0][0], $points[0][1], $pairs[0][1], (int)$pairs[0][0], false, $lineCol, $labelCol);
        $this->drawPointLabel($im, $points[$n - 1][0], $points[$n - 1][1], $pairs[$n - 1][1], $member, true, $lineCol, $labelCol);
    }

    /**
     * 折れ線の1点に、点マーカー＋データラベル（上段=日付 M/D、下段=人数）を描く。
     * 文字は元の日付ラベルと同じ控えめスタイル（fontsMedium・muted）。$rightAlign=true で
     * 右端の点用にラベル右端を点に合わせる（左端は左揃え）。いずれも枠外へ出ないようクランプ。
     */
    private function drawPointLabel(\GdImage $im, int $px, int $py, ?string $ymd, int $member, bool $rightAlign, int $dotCol, int $textCol): void
    {
        imagefilledellipse($im, $px, $py, 16, 16, $dotCol);

        $dateStr = $this->shortDate($ymd);
        $valStr = number_format($member) . t('人');
        $dateSize = 22;
        $valSize = 24;
        // 下段(人数)を点のすぐ上に、上段(日付)をさらにその上へ積む（$top は各行の ink 上端）。
        // 日付と人数の行間は少し広めに取る。
        $valTop = $py - 16 - $valSize;
        $dateTop = $valTop - 12 - $dateSize;

        $anchor = fn(int $w): int => $rightAlign
            ? min($px + 12, self::WIDTH - 24) - $w   // 右揃え（右端の点）
            : max($px - 12, 24);                     // 左揃え（左端の点）
        if ($dateStr !== '') {
            $this->drawLine($im, $dateStr, $anchor($this->measureLine($dateStr, $dateSize, $this->fontsMedium)), $dateTop, $dateSize, $textCol, $this->fontsMedium);
        }
        $this->drawLine($im, $valStr, $anchor($this->measureLine($valStr, $valSize, $this->fontsMedium)), $valTop, $valSize, $textCol, $this->fontsMedium);
    }
}

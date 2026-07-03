<?php

declare(strict_types=1);

namespace App\Services\OgImage;

/**
 * /recommend/{tag}（テーマ別ランキングページ）用の動的OGP画像（1200x630 PNG）を GD で生成する。
 *
 * 構図（メディア系OGPカードの定番スタイル）:
 *  - 背景: ライトブルー＋淡い斜めストライプの上に、ランキング上位の部屋を「ミニカード」
 *    （白い角丸カード・アイコン＋部屋名＋メンバー数）として散らし、ぼかし＋半透明の青い霞で
 *    沈める（コラージュ感を出しつつ前景の文字を立たせる）
 *  - 前景: 白いバナー帯に特大のネイビー文字を2行（「タグ」のオープンチャット／人気・活発な
 *    部屋ランキング）。タグ部分だけブランドブルーの2トーン。下に細いサブ帯（毎時更新＋ドメイン）
 *  - 左上にサイト名。文言はロケール依存（ja/tw/th）
 *
 * テキスト・絵文字・アイコンの描画機構は AbstractCardImageGenerator（共通基盤）に置いてある。
 */
class RecommendCardImageGenerator extends AbstractCardImageGenerator
{
    /** 背景コラージュに使う部屋数の上限（= CARD_SLOTS のスロット数） */
    public const MAX_ROOMS = 5;

    /** 見出し行の最大/最小フォントサイズ。入らなければ縮小→タグ末尾… で退避 */
    private const HEADLINE_MAX_SIZE = 56;
    private const HEADLINE_MIN_SIZE = 32;

    /** ミニカードの寸法 */
    private const CARD_W = 360;
    private const CARD_H = 170;

    /**
     * ミニカードの左上座標（順位順）。画面端で見切れる配置にしてコラージュ感を出す。
     * 中央帯は前景の白バナーが覆うので上下に散らす。掲載部屋が少ないタグでも偏らないよう、
     * 先頭から「左上→右上→下中央→上中央→左中」の順に埋める。
     * @var array{0:int,1:int}[]
     */
    private const CARD_SLOTS = [
        [24, 104],
        [906, 64],
        [330, 476],
        [478, -54],
        [-56, 396],
    ];

    /**
     * カード画像を生成し PNG バイト列を返す（ファイルには書かない＝CDN側でキャッシュする方針）。
     * 生成できない環境（GD/FreeType/フォント無し）では null を返す（呼び出し側でデフォルト画像に退避）。
     *
     * @param string $tag テーマ名（タグ）
     * @param array{name:string, member:int, iconUrl:?string}[] $rooms ランキング上位の部屋（表示順）
     */
    public function renderPng(string $tag, array $rooms): ?string
    {
        if (!$this->canRender()) {
            return null;
        }

        // --- 背景: ライトブルー＋淡い斜めストライプ ---
        $im = $this->createLightCanvas();

        // --- 部屋のミニカードを散らし、ぼかし＋青い霞で背景に沈める ---
        foreach (array_slice($rooms, 0, self::MAX_ROOMS) as $i => $room) {
            [$x, $y] = self::CARD_SLOTS[$i];
            $this->drawRoomCard($im, $room, $x, $y);
        }
        for ($i = 0; $i < 5; $i++) {
            imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
        }
        imagealphablending($im, true);
        imagefilledrectangle($im, 0, 0, self::WIDTH, self::HEIGHT, imagecolorallocatealpha($im, 214, 229, 250, 58));

        $navy = imagecolorallocate($im, 23, 42, 102);
        $blue = imagecolorallocate($im, 37, 99, 235);
        $subNavy = imagecolorallocate($im, 62, 82, 138);

        // --- 左上: サイト名 ---
        $this->drawLine($im, t('オプチャグラフ'), 56, 34, 30, $navy, $this->fontsBold);

        // --- 中央: 白バナー帯の見出し2行＋サブ帯 ---
        $maxTextW = self::WIDTH - 2 * 56 - 2 * 36; // 両端余白とバナー左右パディングを引いた最大文字幅

        // 1行目「タグ」のオープンチャット: タグ部分だけブランドブルーの2トーン。
        // 入らないタグはサイズを下限まで縮め、それでも溢れる場合はタグ末尾を…で省略
        [$segments, $size] = $this->buildHeadlineSegments($tag, $maxTextW, $navy, $blue);
        $y = $this->drawBanner($im, $segments, $size, 172, $this->fontsBold);

        $y = $this->drawBanner($im, [[t('人気・活発な部屋ランキング'), $navy]], 56, $y + 14, $this->fontsBold);

        $sub = t('1時間ごとに更新') . '　openchat-review.me';
        $this->drawBanner($im, [[$sub, $subNavy]], 30, $y + 14, $this->fontsMedium);

        // --- PNG をバイト列で返す（ファイルには書かない） ---
        ob_start();
        imagepng($im, null, 6);
        return ob_get_clean() ?: null;
    }

    /**
     * 見出し1行目のセグメント（[テキスト, 色] の並び）と、収まるフォントサイズを決める。
     * 文言はロケールの「「%s」のオープンチャット」書式で、タグ部分だけアクセント色にする。
     *
     * @return array{0: array{0:string,1:int}[], 1:int}
     */
    private function buildHeadlineSegments(string $tag, int $maxTextW, int $navy, int $blue): array
    {
        $compose = function (string $t) use ($navy, $blue): array {
            $formatted = sprintfT('「%s」のオープンチャット', $t);
            $pos = mb_strpos($formatted, $t);
            if ($pos === false) {
                return [[$formatted, $navy]];
            }
            return array_values(array_filter([
                [mb_substr($formatted, 0, $pos), $navy],
                [$t, $blue],
                [mb_substr($formatted, $pos + mb_strlen($t)), $navy],
            ], fn(array $seg) => $seg[0] !== ''));
        };
        $width = fn(array $segments, int $size): int => array_sum(
            array_map(fn(array $seg) => $this->measureLine($seg[0], $size, $this->fontsBold), $segments)
        );

        $segments = $compose($tag);
        $size = self::HEADLINE_MAX_SIZE;
        while ($size > self::HEADLINE_MIN_SIZE && $width($segments, $size) > $maxTextW) {
            $size -= 2;
        }
        // 下限サイズでも入らない長いタグは末尾を削って…（書式ごと組み直してロケール差異を保つ）
        while ($tag !== '' && $width($segments, $size) > $maxTextW) {
            $tag = mb_substr($tag, 0, -1);
            $segments = $compose(rtrim($tag) . '…');
        }
        return [$segments, $size];
    }

    /**
     * 中央揃えの白バナー帯を1本描き、その上にセグメント（[テキスト, 色]）を並べる。
     * 帯の幅はテキスト実幅＋左右パディング。戻り値は帯の下端 Y（次の帯の起点用）。
     *
     * @param array{0:string,1:int}[] $segments
     */
    private function drawBanner(\GdImage $im, array $segments, int $size, int $top, array $fontList): int
    {
        $padX = 36;
        $textW = 0;
        foreach ($segments as [$text]) {
            $textW += $this->measureLine($text, $size, $fontList);
        }
        $h = (int)round($size * 1.72);
        $x = intdiv(self::WIDTH - $textW, 2) - $padX;
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, $x, $top, $x + $textW + $padX * 2, $top + $h, $white);

        $cx = $x + $padX;
        $textTop = $top + intdiv($h - (int)round($size * 1.4), 2);
        foreach ($segments as [$text, $color]) {
            $cx += $this->drawLine($im, $text, $cx, $textTop, $size, $color, $fontList);
        }
        return $top + $h;
    }

    /**
     * 部屋のミニカード（白角丸・角丸アイコン＋部屋名2行＋メンバー数）を描く。
     * ぼかし＋霞の下に沈める背景要素なので、細部より「記事カードの気配」を優先した簡素なレイアウト。
     *
     * @param array{name:string, member:int, iconUrl:?string} $room
     */
    private function drawRoomCard(\GdImage $im, array $room, int $x, int $y): void
    {
        $w = self::CARD_W;
        $h = self::CARD_H;
        $white = imagecolorallocate($im, 255, 255, 255);
        $navy = imagecolorallocate($im, 30, 45, 90);
        $muted = imagecolorallocate($im, 110, 125, 160);

        $this->fillRoundedRect($im, $x, $y, $w, $h, 18, $white);

        $pad = 22;
        $iconSize = 72;
        $this->drawRoundedIcon($im, $room['iconUrl'] ?? null, $x + $pad, $y + $pad, $iconSize, 14, imagecolorallocate($im, 208, 220, 240));

        $textX = $x + $pad + $iconSize + 18;
        $textW = $w - ($textX - $x) - $pad;
        $this->drawTitle($im, (string)$room['name'], $textX, $y + $pad + 2, $textW, 22, 2, $navy);

        $memberLabel = sprintfT('メンバー %s人', number_format((int)$room['member']));
        $this->drawLine($im, $memberLabel, $x + $pad, $y + $h - $pad - 26, 20, $muted, $this->fontsMedium);
    }

    /** ライトブルー地に淡い斜めストライプを敷いた 1200x630 のキャンバスを作る。 */
    private function createLightCanvas(): \GdImage
    {
        $im = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefilledrectangle($im, 0, 0, self::WIDTH, self::HEIGHT, imagecolorallocate($im, 205, 222, 247));

        // 45°の帯を一定周期で重ねる（ぼかし後はごく淡いテクスチャになる）
        $band = imagecolorallocate($im, 192, 213, 244);
        $bandW = 56;
        $period = 132;
        for ($x = -self::HEIGHT; $x < self::WIDTH + self::HEIGHT; $x += $period) {
            imagefilledpolygon($im, [
                $x, 0,
                $x + $bandW, 0,
                $x + $bandW - self::HEIGHT, self::HEIGHT,
                $x - self::HEIGHT, self::HEIGHT,
            ], $band);
        }
        return $im;
    }

    /** 角丸長方形を塗る（本体2枚の矩形＋四隅の円） */
    private function fillRoundedRect(\GdImage $im, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        imagefilledrectangle($im, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($im, $x, $y + $r, $x + $w, $y + $h - $r, $color);
        foreach ([[$x + $r, $y + $r], [$x + $w - $r, $y + $r], [$x + $r, $y + $h - $r], [$x + $w - $r, $y + $h - $r]] as [$cx, $cy]) {
            imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $color);
        }
    }

    /**
     * 部屋アイコンを角丸スクエアにクロップして描く。取得失敗時はプレースホルダの角丸を描く。
     * クロップは円形版（drawIcon）と同じマスク方式（魔法色の外側をスキップして転写）。
     */
    private function drawRoundedIcon(\GdImage $im, ?string $iconUrl, int $x, int $y, int $size, int $r, int $fallbackCol): void
    {
        $src = $iconUrl ? $this->loadIcon($iconUrl) : null;
        if (!$src) {
            $this->fillRoundedRect($im, $x, $y, $size, $size, $r, $fallbackCol);
            return;
        }

        $sq = imagecreatetruecolor($size, $size);
        imagecopyresampled($sq, $src, 0, 0, 0, 0, $size, $size, imagesx($src), imagesy($src));

        $mask = imagecreatetruecolor($size, $size);
        imagefill($mask, 0, 0, imagecolorallocate($mask, 1, 2, 3));
        $this->fillRoundedRect($mask, 0, 0, $size, $size, $r, imagecolorallocate($mask, 255, 255, 255));

        for ($iy = 0; $iy < $size; $iy++) {
            for ($ix = 0; $ix < $size; $ix++) {
                if ((imagecolorat($mask, $ix, $iy) & 0xFFFFFF) === 0x010203) {
                    continue;
                }
                imagesetpixel($im, $x + $ix, $y + $iy, imagecolorat($sq, $ix, $iy));
            }
        }
    }
}

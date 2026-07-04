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
 *    部屋ランキング）。タグ部分だけブランドブルーの2トーン。下に細いサブ帯（毎時更新＋サイト名）
 *  - 左上にはサイト名を置かない（oc カードの左上サイト名廃止と同方針・文言はロケール依存 ja/tw/th）
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

    /** 背景ミニカードのアイコン取得に使ってよい合計秒数（超過分はプレースホルダで描く） */
    private const ICON_TOTAL_BUDGET = 6;

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
        // アイコン取得は1件ずつ直列（最大3秒×5件）なので、合計の時間予算を使い切ったら
        // 残りはプレースホルダにしてワーカーの長時間拘束を防ぐ（背景要素なので欠けてよい）
        $iconDeadline = microtime(true) + self::ICON_TOTAL_BUDGET;
        foreach (array_slice($rooms, 0, self::MAX_ROOMS) as $i => $room) {
            [$x, $y] = self::CARD_SLOTS[$i];
            if (microtime(true) > $iconDeadline) {
                $room['iconUrl'] = null;
            }
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

        // サイト名はサブ帯に含まれるため左上には置かない（oc カードの左上サイト名廃止と同方針）

        // --- 中央: 白バナー帯の見出し2行＋サブ帯 ---
        $maxTextW = self::WIDTH - 2 * 56 - 2 * 36; // 両端余白とバナー左右パディングを引いた最大文字幅

        // 1行目「タグ」のオープンチャット: タグ部分だけブランドブルーの2トーン。
        // 入らないタグはサイズを下限まで縮め、それでも溢れる場合はタグ末尾を…で省略
        [$segments, $size] = $this->buildTagSegments(
            '「%s」のオープンチャット',
            $tag,
            $maxTextW,
            self::HEADLINE_MAX_SIZE,
            self::HEADLINE_MIN_SIZE,
            $navy,
            $blue,
        );
        $y = $this->drawBanner($im, $segments, $size, 172, $this->fontsBold);

        $y = $this->drawBanner($im, [[t('人気・活発な部屋ランキング'), $navy]], 56, $y + 14, $this->fontsBold);

        // サブ帯: 更新頻度（細字）＋ブランド名（太字）。URLは出さない
        $this->drawBanner($im, [
            [t('1時間ごとに更新') . '　', $subNavy],
            [t('オプチャグラフ'), $navy, $this->fontsBold],
        ], 30, $y + 14, $this->fontsMedium);

        // --- PNG をバイト列で返す（ファイルには書かない） ---
        return $this->encodePng($im);
    }

    /**
     * 検索用 1:1 サムネイル（640x640 PNG）を生成する（meta name="thumbnail" 用）。
     * 検索結果では小さく表示されるため、部屋カードのコラージュは使わず、OGP と同じライトブルー
     * ストライプ地に白バナー3段（「タグ」・ランキングラベル・サイト名）だけを縦中央に積む。
     * 生成できない環境では null（呼び出し側でデフォルト画像に退避）。
     */
    public function renderThumbPng(string $tag): ?string
    {
        if (!$this->canRender()) {
            return null;
        }

        $s = self::THUMB_SIZE;
        $im = $this->createLightCanvas($s, $s);

        $navy = imagecolorallocate($im, 23, 42, 102);
        $blue = imagecolorallocate($im, 37, 99, 235);
        $subNavy = imagecolorallocate($im, 62, 82, 138);

        $maxTextW = $s - 2 * 28 - 2 * 36;
        [$segments, $size] = $this->buildTagSegments('「%s」', $tag, $maxTextW, 64, 32, $navy, $blue);

        // 3段の帯の合計高さから開始位置を決めて縦中央に置く
        $gap = 12;
        $total = (int)round($size * 1.72) + $gap + (int)round(26 * 1.72) + $gap + (int)round(22 * 1.72);
        $y = intdiv($s - $total, 2);
        $y = $this->drawBanner($im, $segments, $size, $y, $this->fontsBold, $s);
        $y = $this->drawBanner($im, [[t('人気・活発な部屋ランキング'), $navy]], 26, $y + $gap, $this->fontsBold, $s);
        $this->drawBanner($im, [[t('オプチャグラフ'), $subNavy]], 22, $y + $gap, $this->fontsMedium, $s);

        return $this->encodePng($im);
    }

    /**
     * タグ入り見出しのセグメント（[テキスト, 色] の並び）と、収まるフォントサイズを決める。
     * ロケールの $format（%s にタグが入る翻訳キー）で整形し、タグ部分だけアクセント色にする。
     * サイズを下限まで縮めても入らない長いタグは末尾を…で省略する。
     *
     * @return array{0: array{0:string,1:int}[], 1:int}
     */
    private function buildTagSegments(string $format, string $tag, int $maxTextW, int $maxSize, int $minSize, int $navy, int $blue): array
    {
        $compose = function (string $t) use ($format, $navy, $blue): array {
            // 訳文テンプレートの %s の位置で前後を割る。整形後の文字列からタグを検索すると、
            // テンプレート側のリテラル（例: th の "OpenChat"）にタグが部分一致して2トーンの
            // 塗り分け位置がずれるため、必ず %s 基準で分割する
            $tpl = t($format);
            $pos = mb_strpos($tpl, '%s');
            if ($pos === false) {
                return [[sprintf($tpl, $t), $navy]];
            }
            return array_values(array_filter([
                [mb_substr($tpl, 0, $pos), $navy],
                [$t, $blue],
                [mb_substr($tpl, $pos + 2), $navy],
            ], fn(array $seg) => $seg[0] !== ''));
        };
        $width = fn(array $segments, int $size): int => array_sum(
            array_map(fn(array $seg) => $this->measureLine($seg[0], $size, $this->fontsBold), $segments)
        );

        $segments = $compose($tag);
        $size = $maxSize;
        while ($size > $minSize && $width($segments, $size) > $maxTextW) {
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
     * 中央揃えの白バナー帯を1本描き、その上にセグメント（[テキスト, 色, ?フォント]）を並べる。
     * 帯の幅はテキスト実幅＋左右パディング。縦位置は「一度白地のスクラッチに描いて ink の実ピクセル
     * 範囲を測り、その ink が帯の縦中央に来るようコピーする」方式で合わせる（フォントメトリクスの
     * 推定だと CJK・タイ文字・絵文字混在で中央からずれるため、描画結果そのものを基準にする）。
     * 戻り値は帯の下端 Y（次の帯の起点用）。
     *
     * @param array{0:string, 1:int, 2?:array} $segments セグメントごとにフォントリストを上書き可
     * @param array $fontList フォント指定の無いセグメントに使うフォントリスト
     */
    private function drawBanner(\GdImage $im, array $segments, int $size, int $top, array $fontList, int $canvasW = self::WIDTH): int
    {
        $padX = 36;
        $textW = 0;
        foreach ($segments as $seg) {
            $textW += $this->measureLine($seg[0], $size, $seg[2] ?? $fontList);
        }
        $h = (int)round($size * 1.72);
        $x = intdiv($canvasW - $textW, 2) - $padX;
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, $x, $top, $x + $textW + $padX * 2, $top + $h, $white);

        // 白地スクラッチに実際に描いて ink の上下端を実測する（上下に1文字分の余白を確保）
        $scratchW = max(1, $textW + 4);
        $scratchH = $size * 3;
        $scratch = imagecreatetruecolor($scratchW, $scratchH);
        imagefilledrectangle($scratch, 0, 0, $scratchW, $scratchH, imagecolorallocate($scratch, 255, 255, 255));
        $cx = 2;
        foreach ($segments as $seg) {
            $color = imagecolorallocate($scratch, ($seg[1] >> 16) & 0xFF, ($seg[1] >> 8) & 0xFF, $seg[1] & 0xFF);
            $cx += $this->drawLine($scratch, $seg[0], $cx, $size, $size, $color, $seg[2] ?? $fontList);
        }

        $inkTop = null;
        $inkBottom = null;
        for ($y = 0; $y < $scratchH; $y++) {
            for ($sx = 0; $sx < $scratchW; $sx += 2) {
                if ((imagecolorat($scratch, $sx, $y) & 0xFFFFFF) !== 0xFFFFFF) {
                    $inkTop ??= $y;
                    $inkBottom = $y;
                    break;
                }
            }
        }
        if ($inkTop === null) {
            return $top + $h; // 描く ink が無い（空文字など）＝帯だけ
        }

        // ink が帯の縦中央に来る位置へスクラッチを転写（帯もスクラッチも白地なので継ぎ目は出ない）
        $inkH = $inkBottom - $inkTop + 1;
        imagecopy($im, $scratch, $x + $padX - 2, $top + intdiv($h - $inkH, 2), 0, $inkTop, $scratchW, $inkH);
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

    /** ライトブルー地に淡い斜めストライプを敷いたキャンバス（省略時 1200x630）を作る。 */
    private function createLightCanvas(int $w = self::WIDTH, int $h = self::HEIGHT): \GdImage
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 205, 222, 247));

        // 45°の帯を一定周期で重ねる（ぼかし後はごく淡いテクスチャになる）
        $band = imagecolorallocate($im, 192, 213, 244);
        $bandW = 56;
        $period = 132;
        for ($x = -$h; $x < $w + $h; $x += $period) {
            imagefilledpolygon($im, [
                $x, 0,
                $x + $bandW, 0,
                $x + $bandW - $h, $h,
                $x - $h, $h,
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

        $this->copyMaskedIcon($im, $src, $x, $y, $size, function (\GdImage $mask, int $white) use ($size, $r) {
            $this->fillRoundedRect($mask, 0, 0, $size, $size, $r, $white);
        });
    }
}

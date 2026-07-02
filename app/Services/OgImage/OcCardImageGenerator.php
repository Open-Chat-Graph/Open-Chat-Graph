<?php

declare(strict_types=1);

namespace App\Services\OgImage;

use App\Services\Crawler\FileDownloader;

/**
 * ルーム個別ページ用の動的OGP画像（1200x630 PNG）を GD で生成する。
 *
 * レイアウト: 左に部屋アイコン、その右に「ヘッダー(メンバー数＋7日増減) 1行」＋「部屋名 最大2行
 * （日本語・比例フォント・はみ出しは … で省略）」。下段に30日メンバー数スパークライン。
 *
 * 日本語描画には Noto Sans CJK JP を storage/font 同梱で使う（本番/ローカルで同一描画）。
 * ラテン・数字も含むので数値・記号も同じフォントで描く。絵文字だけ NotoColorEmoji から合成する。
 */
class OcCardImageGenerator
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;

    /** タイトルの自動縮小の下限フォントサイズ（これでも収まらなければ … で省略） */
    private const TITLE_MIN_SIZE = 30;

    /** アイコン取得の総時間上限（秒・接続〜受信完了）。落ちてもプレースホルダで生成を続行する */
    private const ICON_MAX_DURATION = 3;

    /** アイコン取得の通信アイドル上限（秒） */
    private const ICON_IDLE_TIMEOUT = 2;

    /** アイコン取得用UA（クローラーと同一の OpenChatStatsbot 名義） */
    private const ICON_FETCH_UA = 'Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36 (compatible; OpenChatStatsbot; +https://github.com/Open-Chat-Graph/Open-Chat-Graph)';

    private string $fontBold;
    private string $fontMedium;
    private string $fontSymbol;
    private NotoColorEmojiReader $emoji;

    public function __construct(
        private FileDownloader $fileDownloader,
    ) {
        $dir = __DIR__ . '/../../../storage/font';
        $this->fontBold = $dir . '/NotoSansJP-Bold.ttf';
        $this->fontMedium = $dir . '/NotoSansJP-Regular.ttf';
        // 絵文字でない記号（❥ 等の Dingbats など）で Noto CJK にも絵文字にも無いものの受け皿（モノクロ）
        $this->fontSymbol = $dir . '/NotoSansSymbols2.ttf';
        $this->emoji = new NotoColorEmojiReader($dir . '/NotoColorEmoji.ttf');
    }

    /**
     * カード画像を生成し PNG バイト列を返す（ファイルには書かない＝CDN側でキャッシュする方針）。
     * 生成できない環境（GD/FreeType/フォント無し）では null を返す（呼び出し側でデフォルト画像に退避）。
     *
     * @param string     $name     部屋名（日本語可・最大2行に折り返し、はみ出しは省略）
     * @param int        $member   現在メンバー数
     * @param int|null   $diffWeek 直近7日のメンバー増減（不明は null＝非表示）
     * @param int[]      $series   スパークライン用のメンバー数系列（日付昇順・30日程度、空なら非表示）
     * @param ?string    $iconUrl  部屋アイコンURL（取得失敗・null はプレースホルダ円）
     */
    public function renderPng(string $name, int $member, ?int $diffWeek, array $series, ?string $iconUrl): ?string
    {
        // 描画能力を事前に control-flow で判定する（例外に頼らない）。FreeType 関数まで確認するのは
        // GD が FreeType 無しビルドだと imagettftext が警告→（このアプリのハンドラで）例外になるため。
        if (
            !function_exists('imagecreatetruecolor')
            || !function_exists('imagettftext')
            || !is_file($this->fontBold)
            || !is_file($this->fontMedium)
        ) {
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

        // --- スパークライン（下段・先に描いて他要素を上に重ねる） ---
        $this->drawSparkline($im, $series, $accent);

        // --- 部屋アイコン（左上・円形クロップ） ---
        $iconSize = 190;
        $iconX = 72;
        $iconY = 66;
        $this->drawIcon($im, $iconUrl, $iconX, $iconY, $iconSize, $accent);

        $rightX = $iconX + $iconSize + 40; // = 302
        $rightEdge = self::WIDTH - 72;      // = 1128

        // --- ヘッダー1行目: メンバー数（muted）＋ 7日増減（緑/赤） ---
        $headHead = 'メンバー ' . number_format($member) . '人';
        $headY = $iconY + 44;
        $advance = $this->drawText($im, $headHead, $rightX, $headY, $this->fontMedium, 30, $sub);
        if ($diffWeek !== null) {
            $isUp = $diffWeek >= 0;
            $growth = ($isUp ? '▲ +' : '▼ ') . number_format($diffWeek) . ' / 7日';
            $this->drawText($im, $growth, $rightX + $advance + 24, $headY, $this->fontBold, 30, $isUp ? $green : $red);
        }

        // --- タイトル: 部屋名（最大2行。48pxで収まらなければ自動縮小。テキスト=Noto / 絵文字=カラー / 記号=モノクロ） ---
        $this->drawTitle($im, $name, $rightX, $headY + 38, $rightEdge - $rightX, 48, 2, $white);

        // --- ブランドフッター（右下） ---
        $brand = 'openchat-review.me';
        $bw = $this->textWidth($brand, $this->fontMedium, 28);
        $this->drawText($im, $brand, self::WIDTH - $bw - 56, self::HEIGHT - 42, $this->fontMedium, 28, $sub);

        // --- PNG をバイト列で返す（ファイルには書かない） ---
        ob_start();
        imagepng($im, null, 6);
        return ob_get_clean() ?: null;
    }

    /**
     * 左上原点・指定サイズでテキストを1行描画し、次の文字の開始 x までの送り幅を返す。
     * imagettftext はベースライン基準なので、上端 $top から文字高ぶん下げて描く。
     */
    private function drawText(\GdImage $im, string $text, int $x, int $top, string $font, int $size, int $color): int
    {
        if ($text === '') {
            return 0;
        }
        $box = imagettfbbox($size, 0, $font, $text);
        $ascent = -$box[7]; // ベースラインから上端までの高さ
        imagettftext($im, $size, 0, $x, $top + $ascent, $color, $font, $text);
        return $box[2] - $box[0]; // 送り幅（おおよそ）
    }

    /**
     * 部屋名タイトルを描く。「テキスト(Noto)」と「カラー絵文字(NotoColorEmoji のPNG)」を混在させ、
     * $maxWidth で折り返して $maxLines 行に収める（溢れは末尾を … で省略）。
     *
     * テキストは1文字ずつではなく“連続する文字のかたまり(ラン)”を imagettftext で一括描画するため、
     * フォント本来の字間(カーニング)が保たれる（1文字ずつ描くと字間が詰まる問題を回避）。絵文字で
     * ランが途切れる。フォントにも絵文字にも無い文字はスキップ（豆腐を出さない）。
     */
    private function drawTitle(\GdImage $im, string $name, int $x, int $topY, int $maxWidth, int $maxSize, int $maxLines, int $color): void
    {
        // 1) セグメント化（サイズ非依存）: フォールバック（Noto CJK → カラー絵文字 → 記号フォント）で
        //    振り分け、連続する「同じフォントのテキスト」を1つのラン(文字列)にまとめる。絵文字はランを切る。
        $textRanges = $this->supportedRanges();
        $symRanges = $this->supportedSymbolRanges();
        $segments = [];
        $buf = '';
        $bufFont = null;
        $flush = function () use (&$segments, &$buf, &$bufFont) {
            if ($buf !== '') {
                $segments[] = ['emoji' => false, 'str' => $buf, 'font' => $bufFont];
                $buf = '';
                $bufFont = null;
            }
        };
        foreach (preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            $cp = mb_ord($ch, 'UTF-8');
            if ($cp === false || $cp < 0x20) {
                continue;
            }
            $font = null;
            $png = null;
            if ($textRanges === null || $this->cpInRanges($cp, $textRanges)) {
                $font = $this->fontBold;
            } elseif (($png = $this->emoji->getPng($cp)) !== null) {
                // カラー絵文字
            } elseif ($symRanges !== null && $this->cpInRanges($cp, $symRanges)) {
                $font = $this->fontSymbol;
            } else {
                continue; // どれでも出せない字はスキップ
            }
            if ($png !== null) {
                $flush();
                $segments[] = ['emoji' => true, 'png' => $png];
            } else {
                if ($bufFont !== null && $bufFont !== $font) {
                    $flush();
                }
                $buf .= $ch;
                $bufFont = $font;
            }
        }
        $flush();

        // 2) フォントサイズを最大から下げ、$maxLines 行に溢れず収まる最大サイズを採用（自動縮小）
        $size = $maxSize;
        $lines = [];
        for (; $size >= self::TITLE_MIN_SIZE; $size -= 2) {
            [$lines, $overflow] = $this->layoutTitleLines($segments, $size, $maxWidth, $maxLines);
            if (!$overflow) {
                break;
            }
        }

        // 3) 描画（テキストはランごとに一括＝フォント本来の字間を保つ）
        $lineHeight = (int)round($size * 1.44);
        $emojiDraw = (int)round($size * 0.98);
        $emojiW = (int)round($size * 1.14);
        imagealphablending($im, true);
        foreach ($lines as $lineIdx => $line) {
            $baseY = $topY + $lineHeight * ($lineIdx + 1);
            $cx = $x;
            foreach ($line as $op) {
                if ($op['emoji']) {
                    $this->composeEmoji($im, $op['png'], $cx + intdiv($emojiW - $emojiDraw, 2), $baseY - $size, $emojiDraw);
                } else {
                    imagettftext($im, $size, 0, $cx, $baseY, $color, $op['font'], $op['str']);
                }
                $cx += $op['w'];
            }
        }
    }

    /**
     * 指定サイズでセグメントを $maxWidth・$maxLines に行組みする。テキストランは必要に応じて
     * 文字単位で分割して折り返す。溢れたら最終行末尾を … にして [$lines, true] を返す。
     *
     * @param array<int,array<string,mixed>> $segments
     * @return array{0: array<int,array<int,array<string,mixed>>>, 1: bool}
     */
    private function layoutTitleLines(array $segments, int $size, int $maxWidth, int $maxLines): array
    {
        $emojiW = (int)round($size * 1.14);
        $gap = (int)round($size * 0.05);

        $lines = [[]];
        $lineW = 0;
        $overflow = false;
        $newline = function () use (&$lines, &$lineW, &$overflow, $maxLines): bool {
            if (count($lines) >= $maxLines) {
                $overflow = true;
                return false;
            }
            $lines[] = [];
            $lineW = 0;
            return true;
        };
        foreach ($segments as $seg) {
            if ($overflow) {
                break;
            }
            if ($seg['emoji']) {
                if ($lineW > 0 && $lineW + $emojiW > $maxWidth && !$newline()) {
                    break;
                }
                $lines[count($lines) - 1][] = ['emoji' => true, 'png' => $seg['png'], 'w' => $emojiW];
                $lineW += $emojiW;
                continue;
            }
            $rest = $seg['str'];
            $font = $seg['font'];
            while ($rest !== '' && !$overflow) {
                $prefix = $this->fitPrefix($rest, $font, $size, $maxWidth - $lineW - $gap);
                if ($prefix === '') {
                    if ($lineW === 0) {
                        $prefix = mb_substr($rest, 0, 1);
                    } elseif (!$newline()) {
                        break;
                    } else {
                        continue;
                    }
                }
                $w = $this->advanceWidth($prefix, $font, $size) + $gap;
                $lines[count($lines) - 1][] = ['emoji' => false, 'str' => $prefix, 'font' => $font, 'w' => $w];
                $lineW += $w;
                $rest = mb_substr($rest, mb_strlen($prefix));
                if ($rest !== '' && !$newline()) {
                    break;
                }
            }
        }

        if ($overflow) {
            $ellipsisW = $this->advanceWidth('…', $this->fontBold, $size) + $gap;
            $li = count($lines) - 1;
            $w = array_sum(array_column($lines[$li], 'w'));
            while ($lines[$li] && $w + $ellipsisW > $maxWidth) {
                $w -= array_pop($lines[$li])['w'];
            }
            $lines[$li][] = ['emoji' => false, 'str' => '…', 'font' => $this->fontBold, 'w' => $ellipsisW];
        }

        return [$lines, $overflow];
    }

    /** 描画したときの送り幅（ink右端＝おおよそのペン送り）。重なり防止のため ink幅ではなくこれで送る。 */
    private function advanceWidth(string $text, string $font, int $size): int
    {
        if ($text === '') {
            return 0;
        }
        return imagettfbbox($size, 0, $font, $text)[2];
    }

    /** $text の先頭から、送り幅が $maxWidth に収まる最長プレフィックスを返す（入らなければ ''） */
    private function fitPrefix(string $text, string $font, int $size, int $maxWidth): string
    {
        if ($maxWidth <= 0) {
            return '';
        }
        $lo = 0;
        $hi = mb_strlen($text);
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi + 1, 2);
            if ($this->advanceWidth(mb_substr($text, 0, $mid), $font, $size) <= $maxWidth) {
                $lo = $mid;
            } else {
                $hi = $mid - 1;
            }
        }
        return $lo === 0 ? '' : mb_substr($text, 0, $lo);
    }

    /** 記号フォント(NotoSansSymbols2)が持つコードポイント範囲（プロセス内キャッシュ） */
    private function supportedSymbolRanges(): ?array
    {
        static $cache = null;
        static $loaded = false;
        if ($loaded) {
            return $cache;
        }
        $loaded = true;
        $cache = is_file($this->fontSymbol) ? $this->readCmapRanges($this->fontSymbol) : null;
        return $cache;
    }

    /** カラー絵文字PNG(NotoColorEmoji由来)を $size 四方に縮小してアルファ合成する。ベースライン合わせで少し下げる。 */
    private function composeEmoji(\GdImage $im, string $png, int $x, int $topY, int $size): void
    {
        try {
            $src = imagecreatefromstring($png);
        } catch (\ErrorException $e) {
            return; // 想定外の壊れ画像はスキップ（豆腐は出さない）
        }
        if ($src === false) {
            return;
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $dh = (int)round($size * $sh / max(1, $sw)); // アスペクト維持
        // テキストのベースライン(=topY+size)に対し、少し持ち上げてキャップ高に合わせる
        $dy = $topY + (int)round($size * 0.12);
        imagecopyresampled($im, $src, $x, $dy, 0, 0, $size, $dh, $sw, $sh);
    }

    /** タイトル用フォントの cmap から「対応コードポイントの範囲リスト」を返す（プロセス内キャッシュ） */
    private function supportedRanges(): ?array
    {
        static $cache = null;
        static $loaded = false;
        if ($loaded) {
            return $cache;
        }
        $loaded = true;
        $cache = $this->readCmapRanges($this->fontBold);
        return $cache;
    }

    /** @param array<int,array{0:int,1:int}> $ranges 昇順ソート済みの [start,end] 範囲 */
    private function cpInRanges(int $cp, array $ranges): bool
    {
        $lo = 0;
        $hi = count($ranges) - 1;
        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            if ($cp < $ranges[$mid][0]) {
                $hi = $mid - 1;
            } elseif ($cp > $ranges[$mid][1]) {
                $lo = $mid + 1;
            } else {
                return true;
            }
        }
        return false;
    }

    /**
     * TTF の cmap テーブルを読み、対応コードポイントの [start,end] 範囲リスト（昇順）を返す。
     * 必要な部分だけ fseek/fread で読む（5MB 全読みを避ける）。解析できなければ null。
     * format 4(BMP) / 12(全域) の Unicode サブテーブルに対応。
     *
     * @return array<int,array{0:int,1:int}>|null
     */
    private function readCmapRanges(string $path): ?array
    {
        $fp = @fopen($path, 'rb');
        if ($fp === false) {
            return null;
        }
        try {
            $u16 = fn(string $b, int $o) => (ord($b[$o]) << 8) | ord($b[$o + 1]);
            $u32 = fn(string $b, int $o) => (ord($b[$o]) << 24) | (ord($b[$o + 1]) << 16) | (ord($b[$o + 2]) << 8) | ord($b[$o + 3]);

            $head = fread($fp, 12);
            if (strlen($head) < 12) {
                return null;
            }
            $numTables = $u16($head, 4);
            $records = fread($fp, $numTables * 16);
            $cmapOff = null;
            for ($i = 0; $i < $numTables; $i++) {
                $rec = substr($records, $i * 16, 16);
                if (strlen($rec) < 16) {
                    break;
                }
                if (substr($rec, 0, 4) === 'cmap') {
                    $cmapOff = $u32($rec, 8);
                    break;
                }
            }
            if ($cmapOff === null) {
                return null;
            }

            fseek($fp, $cmapOff);
            $ch = fread($fp, 4);
            if (strlen($ch) < 4) {
                return null;
            }
            $numSub = $u16($ch, 2);
            $subRecs = fread($fp, $numSub * 8);
            $bestOff = null;
            $bestScore = -1;
            for ($i = 0; $i < $numSub; $i++) {
                $r = substr($subRecs, $i * 8, 8);
                if (strlen($r) < 8) {
                    break;
                }
                $pid = $u16($r, 0);
                $eid = $u16($r, 2);
                $off = $u32($r, 4);
                $score = ($pid === 3 && $eid === 10) ? 4
                    : (($pid === 3 && $eid === 1) ? 3
                    : (($pid === 0) ? 2
                    : (($pid === 3 && $eid === 0) ? 1 : 0)));
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestOff = $cmapOff + $off;
                }
            }
            if ($bestOff === null) {
                return null;
            }

            fseek($fp, $bestOff);
            $fmtBuf = fread($fp, 2);
            if (strlen($fmtBuf) < 2) {
                return null;
            }
            $format = $u16($fmtBuf, 0);
            $ranges = [];

            if ($format === 4) {
                $hdr = fread($fp, 12); // length,language,segCountX2,searchRange,entrySelector,rangeShift
                $segX2 = $u16($hdr, 4);
                $segCount = intdiv($segX2, 2);
                $endCodes = fread($fp, $segX2);
                fread($fp, 2); // reservedPad
                $startCodes = fread($fp, $segX2);
                for ($s = 0; $s < $segCount; $s++) {
                    $end = $u16($endCodes, $s * 2);
                    $start = $u16($startCodes, $s * 2);
                    if ($start === 0xFFFF || $start > $end) {
                        continue;
                    }
                    $ranges[] = [$start, $end];
                }
            } elseif ($format === 12) {
                $hdr = fread($fp, 14); // reserved,length,language,nGroups
                $nGroups = $u32($hdr, 10);
                $nGroups = min($nGroups, 200000); // 安全上限
                $groups = fread($fp, $nGroups * 12);
                for ($g = 0; $g < $nGroups; $g++) {
                    $o = $g * 12;
                    if ($o + 8 > strlen($groups)) {
                        break;
                    }
                    $ranges[] = [$u32($groups, $o), $u32($groups, $o + 4)];
                }
            } else {
                return null;
            }

            if (!$ranges) {
                return null;
            }
            usort($ranges, fn($a, $b) => $a[0] <=> $b[0]);
            return $ranges;
        } finally {
            fclose($fp);
        }
    }

    /** テキストの描画幅（px）を返す */
    private function textWidth(string $text, string $font, int $size): int
    {
        if ($text === '') {
            return 0;
        }
        $box = imagettfbbox($size, 0, $font, $text);
        return $box[2] - $box[0];
    }

    /**
     * 30日メンバー数スパークライン（塗りつぶし付き折れ線）を下部に描画する。系列が2点未満なら描かない。
     *
     * @param int[] $series
     */
    private function drawSparkline(\GdImage $im, array $series, int $lineCol): void
    {
        $series = array_values(array_filter($series, fn($v) => $v !== null));
        $n = count($series);
        if ($n < 2) {
            return;
        }

        $left = 72;
        $right = self::WIDTH - 72;
        $topY = 400;
        $bottomY = self::HEIGHT - 96;

        $min = min($series);
        $max = max($series);
        $range = max(1, $max - $min);
        $pad = (int)($range * 0.15);
        $min -= $pad;
        $range = max(1, $max - $min);

        $points = [];
        for ($i = 0; $i < $n; $i++) {
            $x = (int)($left + ($right - $left) * $i / ($n - 1));
            $y = (int)($bottomY - ($bottomY - $topY) * ($series[$i] - $min) / $range);
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
    }

    /**
     * 部屋アイコンを円形にクロップして描画する。取得失敗時はプレースホルダの円を描く。
     */
    private function drawIcon(\GdImage $im, ?string $iconUrl, int $x, int $y, int $size, int $fallbackCol): void
    {
        $src = $iconUrl ? $this->loadIcon($iconUrl) : null;

        if (!$src) {
            imagefilledellipse($im, $x + (int)($size / 2), $y + (int)($size / 2), $size, $size, $fallbackCol);
            return;
        }

        // 正方形へリサイズ
        $sq = imagecreatetruecolor($size, $size);
        imagecopyresampled($sq, $src, 0, 0, 0, 0, $size, $size, imagesx($src), imagesy($src));

        // 円形マスク: 円の外側を魔法色で塗り、内側だけ転写する（簡易アンチエイリアス無し）
        $mask = imagecreatetruecolor($size, $size);
        imagefill($mask, 0, 0, imagecolorallocate($mask, 1, 2, 3));
        imagefilledellipse($mask, (int)($size / 2), (int)($size / 2), $size, $size, imagecolorallocate($mask, 255, 255, 255));

        for ($iy = 0; $iy < $size; $iy++) {
            for ($ix = 0; $ix < $size; $ix++) {
                if ((imagecolorat($mask, $ix, $iy) & 0xFFFFFF) === 0x010203) {
                    continue; // 円の外はスキップ
                }
                imagesetpixel($im, $x + $ix, $y + $iy, imagecolorat($sq, $ix, $iy));
            }
        }
    }

    /**
     * 外部（LINE CDN）から部屋アイコンを取得してデコードする。取得できなければ null（→プレースホルダ）。
     *
     * 取得はプロジェクト共通の FileDownloader（Symfony HttpClient）に寄せ、Web リクエスト経路で
     * ワーカーを長く拘束しないよう timeout/max_duration・retry無し・redirect少で呼ぶ。
     * ここは「信頼できない外部入力」を扱う唯一の場所:
     *  - 取得失敗（404=false / サーバ・通信エラー=\RuntimeException）は想定内なので null に落とす
     *  - デコードは、シグネチャが画像でも破損データだと imagecreatefromstring が警告→例外になりうるため、
     *    その一点だけ \ErrorException を受けて null にする
     * いずれも「想定内の外部入力エラー処理」であって、内部バグの握りつぶしではない。
     */
    private function loadIcon(string $url): ?\GdImage
    {
        try {
            $data = $this->fileDownloader->downloadFile(
                $url,
                self::ICON_FETCH_UA,
                max_redirects: 2,
                retryLimit: 1,
                retryInterval: 0,
                method: 'GET',
                timeout: self::ICON_IDLE_TIMEOUT,
                maxDuration: self::ICON_MAX_DURATION,
            );
        } catch (\RuntimeException $e) {
            return null; // 通信・サーバエラー（想定内）→ プレースホルダ
        }

        if ($data === false || !$this->looksLikeImage($data)) {
            return null;
        }

        try {
            $src = imagecreatefromstring($data);
        } catch (\ErrorException $e) {
            return null; // 破損した外部画像（想定内）→ プレースホルダ
        }
        return $src === false ? null : $src;
    }

    /** 先頭バイトのシグネチャで画像（JPEG/PNG/GIF/WEBP）かを警告なしに判定する */
    private function looksLikeImage(string $data): bool
    {
        if (strlen($data) < 12) {
            return false;
        }
        return str_starts_with($data, "\xFF\xD8\xFF")                        // JPEG
            || str_starts_with($data, "\x89PNG\x0D\x0A\x1A\x0A")            // PNG
            || str_starts_with($data, 'GIF87a') || str_starts_with($data, 'GIF89a') // GIF
            || (str_starts_with($data, 'RIFF') && substr($data, 8, 4) === 'WEBP');  // WEBP
    }
}

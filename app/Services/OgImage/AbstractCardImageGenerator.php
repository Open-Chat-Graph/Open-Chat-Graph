<?php

declare(strict_types=1);

namespace App\Services\OgImage;

use App\Services\Crawler\FileDownloader;

/**
 * 動的OGP画像（1200x630 PNG）の共通描画基盤。個別カード（/oc/{id}/card・/recommend/{tag}/card）は
 * これを継承してレイアウトだけを実装する。
 *
 * 提供するもの: 濃紺グラデーション背景のキャンバス生成、多言語テキスト描画（1文字ずつ cmap を持つ
 * 最初のフォントを選ぶフォールバック: 日本語/中国語/ラテン/数字=Noto CJK JP、タイ語=Noto Sans Thai、
 * 絵文字=NotoColorEmoji のカラーPNG、その他の記号=NotoSansSymbols2。どれも無い字はスキップ＝豆腐を
 * 出さない）、タイトルの折り返し・自動縮小・…省略、円形アイコンの取得（LINE CDN）と描画。
 *
 * フォントは storage/font 同梱（本番/ローカルで同一描画）。
 */
abstract class AbstractCardImageGenerator
{
    protected const WIDTH = 1200;
    protected const HEIGHT = 630;

    /** タイトルの自動縮小の下限フォントサイズ（これでも収まらなければ … で省略） */
    protected const TITLE_MIN_SIZE = 30;

    /** アイコン取得の総時間上限（秒・接続〜受信完了）。落ちてもプレースホルダで生成を続行する */
    protected const ICON_MAX_DURATION = 3;

    /** アイコン取得の通信アイドル上限（秒） */
    protected const ICON_IDLE_TIMEOUT = 2;

    /** アイコン取得用UA（クローラーと同一の OpenChatStatsbot 名義） */
    protected const ICON_FETCH_UA = 'Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36 (compatible; OpenChatStatsbot; +https://github.com/Open-Chat-Graph/Open-Chat-Graph)';

    /** 太字テキスト用フォント（1文字ごとに先頭から cmap を持つ最初のフォントを採用＝多言語対応） */
    protected string $fontBold;
    protected string $fontMedium;
    /** @var string[] タイトル・増減用（Noto CJK JP → Thai の順で1文字ずつフォールバック） */
    protected array $fontsBold;
    /** @var string[] ヘッダー・フッター用（同上の Regular） */
    protected array $fontsMedium;
    protected string $fontSymbol;
    protected NotoColorEmojiReader $emoji;

    public function __construct(
        protected FileDownloader $fileDownloader,
    ) {
        $dir = __DIR__ . '/../../../storage/font';
        $this->fontBold = $dir . '/NotoSansJP-Bold.ttf';
        $this->fontMedium = $dir . '/NotoSansJP-Regular.ttf';
        // 多言語フォールバック順: 日本語/中国語/ラテン/数字は Noto CJK JP、タイ語は Noto Sans Thai。
        // 1文字ずつ「その字を持つ最初のフォント」を採用するので、タイ語混じりの部屋名も豆腐にならない。
        $thaiBold = $dir . '/NotoSansThai-Bold.ttf';
        $thaiMedium = $dir . '/NotoSansThai-Regular.ttf';
        $this->fontsBold = array_values(array_filter([$this->fontBold, $thaiBold], 'is_file'));
        $this->fontsMedium = array_values(array_filter([$this->fontMedium, $thaiMedium], 'is_file'));
        // 絵文字でない記号（❥ 等の Dingbats など）で Noto CJK にも絵文字にも無いものの受け皿（モノクロ）
        $this->fontSymbol = $dir . '/NotoSansSymbols2.ttf';
        $this->emoji = new NotoColorEmojiReader($dir . '/NotoColorEmoji.ttf');
    }

    /**
     * 描画能力を事前に control-flow で判定する（例外に頼らない）。FreeType 関数まで確認するのは
     * GD が FreeType 無しビルドだと imagettftext が警告→（このアプリのハンドラで）例外になるため。
     * false のとき呼び出し側はデフォルトOGP画像に退避する。
     */
    protected function canRender(): bool
    {
        return function_exists('imagecreatetruecolor')
            && function_exists('imagettftext')
            && is_file($this->fontBold)
            && is_file($this->fontMedium);
    }

    /** 指定サイズ（省略時 1200x630）のキャンバスを作り、上下方向の濃紺グラデーション背景を敷いて返す。 */
    protected function createCanvas(int $w = self::WIDTH, int $h = self::HEIGHT): \GdImage
    {
        $im = imagecreatetruecolor($w, $h);

        $top = [24, 32, 54];
        $bottom = [10, 14, 26];
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $col = imagecolorallocate(
                $im,
                (int)($top[0] + ($bottom[0] - $top[0]) * $t),
                (int)($top[1] + ($bottom[1] - $top[1]) * $t),
                (int)($top[2] + ($bottom[2] - $top[2]) * $t),
            );
            imageline($im, 0, $y, $w, $y, $col);
        }

        return $im;
    }

    /**
     * 1行テキストを多言語フォールバックで描画し、送り幅を返す（折り返し無し）。ヘッダー/増減/フッター用。
     * テキスト=フォントリスト（先頭から cmap を持つ最初）、絵文字=カラー、記号=モノクロ記号フォント。
     * imagettftext はベースライン基準なので、上端 $top から先頭フォントのアセントぶん下げて描く。
     *
     * @param string[] $fontList 1文字ずつ先頭から試すフォント（Noto CJK → Thai 等）
     */
    protected function drawLine(\GdImage $im, string $text, int $x, int $top, int $size, int $color, array $fontList): int
    {
        if ($text === '' || !$fontList) {
            return 0;
        }
        $segments = $this->segmentText($text, $fontList);
        $baseY = $top + (int)round(-imagettfbbox($size, 0, $fontList[0], 'あ0A')[7]);
        $emojiW = (int)round($size * 1.14);
        $cx = $x;
        imagealphablending($im, true);
        foreach ($segments as $seg) {
            if ($seg['emoji']) {
                $this->composeEmoji($im, $seg['png'], $cx, $baseY - $size, $size);
                $cx += $emojiW;
            } else {
                imagettftext($im, $size, 0, $cx, $baseY, $color, $seg['font'], $seg['str']);
                $cx += $this->advanceWidth($seg['str'], $seg['font'], $size);
            }
        }
        return $cx - $x;
    }

    /** drawLine で描いたときの総送り幅（描画せず計測のみ・右寄せ配置の計算用）。 */
    protected function measureLine(string $text, int $size, array $fontList): int
    {
        if ($text === '' || !$fontList) {
            return 0;
        }
        $emojiW = (int)round($size * 1.14);
        $w = 0;
        foreach ($this->segmentText($text, $fontList) as $seg) {
            $w += $seg['emoji'] ? $emojiW : $this->advanceWidth($seg['str'], $seg['font'], $size);
        }
        return $w;
    }

    /**
     * 文字列を「テキストのラン(同一フォント連続)／カラー絵文字／記号」に分割する（サイズ非依存）。
     * 1文字ごとに $fontList を先頭から見て cmap を持つ最初のフォントをテキストに採用。無ければカラー絵文字、
     * それも無ければ記号フォント、どれも無ければスキップ（豆腐を出さない）。連続する同一フォントは1ラン。
     *
     * @param string[] $fontList
     * @return array<int,array<string,mixed>>
     */
    protected function segmentText(string $text, array $fontList): array
    {
        $symRanges = $this->fontRanges($this->fontSymbol);
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
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $nc = count($chars);
        for ($ci = 0; $ci < $nc; $ci++) {
            $ch = $chars[$ci];
            $cp = mb_ord($ch, 'UTF-8');
            if ($cp === false || $cp < 0x20) {
                continue;
            }
            // 国旗: 地域表示文字(U+1F1E6–U+1F1FF)が2つ続いたら合字グリフ(旗)を1枚の絵文字として描く
            if ($cp >= 0x1F1E6 && $cp <= 0x1F1FF && $ci + 1 < $nc) {
                $cp2 = mb_ord($chars[$ci + 1], 'UTF-8');
                if ($cp2 !== false && $cp2 >= 0x1F1E6 && $cp2 <= 0x1F1FF
                    && ($flag = $this->emoji->getFlagPng($cp, $cp2)) !== null
                ) {
                    $flush();
                    $segments[] = ['emoji' => true, 'png' => $flag];
                    $ci++; // 地域表示文字2つを消費
                    continue;
                }
            }
            $font = $this->resolveFont($cp, $fontList);
            $png = null;
            if ($font === null) {
                if (($png = $this->emoji->getPng($cp)) !== null) {
                    // カラー絵文字
                } elseif ($symRanges !== null && $this->cpInRanges($cp, $symRanges)) {
                    $font = $this->fontSymbol;
                } else {
                    continue; // どれでも出せない字はスキップ
                }
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
        return $segments;
    }

    /** $cp を持つ最初のフォント（cmap 照合）。cmap 解析不能な主フォントは「持つ」とみなす（豆腐より安全側）。 */
    protected function resolveFont(int $cp, array $fontList): ?string
    {
        foreach ($fontList as $i => $font) {
            $ranges = $this->fontRanges($font);
            if ($ranges === null) {
                if ($i === 0) {
                    return $font; // 主フォントの cmap が読めない環境では主フォントで描画を試みる
                }
                continue;
            }
            if ($this->cpInRanges($cp, $ranges)) {
                return $font;
            }
        }
        return null;
    }

    /** フォントの cmap 対応範囲（パス単位でプロセス内キャッシュ）。読めなければ null。 */
    protected function fontRanges(string $path): ?array
    {
        static $cache = [];
        if (array_key_exists($path, $cache)) {
            return $cache[$path];
        }
        return $cache[$path] = is_file($path) ? $this->readCmapRanges($path) : null;
    }

    /**
     * タイトルを描く。「テキスト(Noto)」と「カラー絵文字(NotoColorEmoji のPNG)」を混在させ、
     * $maxWidth で折り返して $maxLines 行に収める（溢れは末尾を … で省略）。
     *
     * テキストは1文字ずつではなく“連続する文字のかたまり(ラン)”を imagettftext で一括描画するため、
     * フォント本来の字間(カーニング)が保たれる（1文字ずつ描くと字間が詰まる問題を回避）。絵文字で
     * ランが途切れる。フォントにも絵文字にも無い文字はスキップ（豆腐を出さない）。
     *
     * @return int 描いた最終行の ink 下端のおおよその Y 座標（後続ブロックの配置基準用）
     */
    protected function drawTitle(\GdImage $im, string $name, int $x, int $topY, int $maxWidth, int $maxSize, int $maxLines, int $color): int
    {
        // 1) セグメント化（サイズ非依存）: 多言語フォントリスト(Noto CJK→Thai)→カラー絵文字→記号フォントで
        //    振り分け、連続する「同じフォントのテキスト」を1ランにまとめる（絵文字はランを切る）。
        $segments = $this->segmentText($name, $this->fontsBold);

        // 2) フォントサイズを最大から下げ、$maxLines 行に溢れず収まる最大サイズを採用（自動縮小）。
        //    $size と $lines を常に一致させる（下限まで縮めても溢れる場合はその下限レイアウト＝末尾…）
        $size = $maxSize;
        [$lines, $overflow] = $this->layoutTitleLines($segments, $size, $maxWidth, $maxLines);
        while ($overflow && $size > self::TITLE_MIN_SIZE) {
            $size -= 2;
            [$lines, $overflow] = $this->layoutTitleLines($segments, $size, $maxWidth, $maxLines);
        }

        // 3) 描画（テキストはランごとに一括＝フォント本来の字間を保つ）
        //    1行目のベースラインは「上端($topY=ink上端) ＋ 実測アセント」に置く（推定係数だとヘッダーに
        //    食い込む）。行間(=行送り)は $size に比例させ、ヘッダーとの間隔とは独立させる。
        $ascent = -imagettfbbox($size, 0, $this->fontBold, '【あA')[7];
        $lineGap = (int)round($size * 1.62);
        $baseY0 = $topY + $ascent;
        $emojiDraw = (int)round($size * 0.98);
        $emojiW = (int)round($size * 1.14);
        imagealphablending($im, true);
        foreach ($lines as $lineIdx => $line) {
            $baseY = $baseY0 + $lineGap * $lineIdx;
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

        // 最終行のベースライン＋ディセント概算（CJK はベースライン下に約 0.12em はみ出す）
        return $baseY0 + $lineGap * (count($lines) - 1) + (int)round($size * 0.12);
    }

    /**
     * 指定サイズでセグメントを $maxWidth・$maxLines に行組みする。テキストランは必要に応じて
     * 文字単位で分割して折り返す。溢れたら最終行末尾を … にして [$lines, true] を返す。
     *
     * @param array<int,array<string,mixed>> $segments
     * @return array{0: array<int,array<int,array<string,mixed>>>, 1: bool}
     */
    protected function layoutTitleLines(array $segments, int $size, int $maxWidth, int $maxLines): array
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
                $lead = $lineW > 0 ? $gap : 0; // 行頭は詰め、途中はセグメント間すき間を入れる
                // 英単語を途中で割らない。入らなければ '' が返るので改行して再挑戦（行頭でも入らない
                // 超長単語だけは文字分割にフォールバック）
                $prefix = $this->fitPrefix($rest, $font, $size, $maxWidth - $lineW - $lead, $lineW === 0);
                if ($prefix === '') {
                    if (!$newline()) {
                        break;
                    }
                    $rest = ltrim($rest, ' ');
                    continue;
                }
                $w = $this->advanceWidth($prefix, $font, $size) + $lead;
                $lines[count($lines) - 1][] = ['emoji' => false, 'str' => rtrim($prefix), 'font' => $font, 'w' => $w];
                $lineW += $w;
                $rest = mb_substr($rest, mb_strlen($prefix));
                if ($rest !== '') {
                    if (!$newline()) {
                        break;
                    }
                    $rest = ltrim($rest, ' ');
                }
            }
        }

        if ($overflow) {
            $ellipsisW = $this->advanceWidth('…', $this->fontBold, $size) + $gap;
            $li = count($lines) - 1;
            // 末尾から「…」の分だけ空ける。絵文字はそのまま外し、テキストランは文字単位で末尾を削る
            // （ランを丸ごと外すと「1行=1ラン」のケースで行全体が消えて「…」だけのタイトルになる）。
            while ($lines[$li]) {
                if (array_sum(array_column($lines[$li], 'w')) + $ellipsisW <= $maxWidth) {
                    break;
                }
                $lastIdx = count($lines[$li]) - 1;
                $last = $lines[$li][$lastIdx];
                if ($last['emoji'] || mb_strlen($last['str']) <= 1) {
                    array_pop($lines[$li]);
                    continue;
                }
                // ランの w にはセグメント間すき間(lead)が含まれるので、テキスト分だけ縮めて維持する
                $lead = max(0, $last['w'] - $this->advanceWidth($last['str'], $last['font'], $size));
                $str = rtrim(mb_substr($last['str'], 0, -1));
                $lines[$li][$lastIdx]['str'] = $str;
                $lines[$li][$lastIdx]['w'] = $lead + $this->advanceWidth($str, $last['font'], $size);
            }
            $lines[$li][] = ['emoji' => false, 'str' => '…', 'font' => $this->fontBold, 'w' => $ellipsisW];
        }

        return [$lines, $overflow];
    }

    /** 描画したときの送り幅（ink右端＝おおよそのペン送り）。重なり防止のため ink幅ではなくこれで送る。 */
    protected function advanceWidth(string $text, string $font, int $size): int
    {
        if ($text === '') {
            return 0;
        }
        return imagettfbbox($size, 0, $font, $text)[2];
    }

    /**
     * $text の先頭から、送り幅が $maxWidth に収まる最長プレフィックスを「区切り可能位置」で返す。
     * 英数字が連続する“単語”の途中では切らない（前の区切りまで戻す）。日本語などは任意位置で切れる。
     * 区切り位置では1つも入らない場合は '' を返す（呼び出し側が改行）。ただし $allowCharBreak=true
     * （行頭で、1単語が1行より長い等）のときは文字単位で入るだけ入れる（最低1文字・無限ループ防止）。
     */
    protected function fitPrefix(string $text, string $font, int $size, int $maxWidth, bool $allowCharBreak): string
    {
        if ($maxWidth <= 0) {
            return $allowCharBreak ? mb_substr($text, 0, 1) : '';
        }
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $n = count($chars);
        if ($n === 0) {
            return '';
        }

        $best = '';
        $acc = '';
        for ($i = 0; $i < $n; $i++) {
            $acc .= $chars[$i];
            if ($this->advanceWidth($acc, $font, $size) > $maxWidth) {
                break;
            }
            // 区切り可能: 末尾、または「今の文字と次の文字が両方とも英数字」ではない位置
            $breakable = ($i === $n - 1)
                || !($this->isWordChar($chars[$i]) && $this->isWordChar($chars[$i + 1]));
            if ($breakable) {
                $best = $acc;
            }
        }
        if ($best !== '') {
            return $best;
        }

        if (!$allowCharBreak) {
            return '';
        }
        // 超長単語: 文字単位で入るだけ（最低1文字）
        $acc = '';
        for ($i = 0; $i < $n; $i++) {
            $t = $acc . $chars[$i];
            if ($this->advanceWidth($t, $font, $size) > $maxWidth) {
                break;
            }
            $acc = $t;
        }
        return $acc === '' ? $chars[0] : $acc;
    }

    /** 英数字（半角 A-Z a-z 0-9）＝単語内文字か。連続する単語内文字の間では改行しない。 */
    protected function isWordChar(string $ch): bool
    {
        return $ch >= '0' && $ch <= 'z' && preg_match('/[A-Za-z0-9]/', $ch) === 1;
    }

    /** カラー絵文字PNG(NotoColorEmoji由来)を $size 四方に縮小してアルファ合成する。ベースライン合わせで少し下げる。 */
    protected function composeEmoji(\GdImage $im, string $png, int $x, int $topY, int $size): void
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

    /** @param array<int,array{0:int,1:int}> $ranges 昇順ソート済みの [start,end] 範囲 */
    protected function cpInRanges(int $cp, array $ranges): bool
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
    protected function readCmapRanges(string $path): ?array
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

    /** 'Y-m-d' を 'n/j'（例 6/3）へ。解釈できなければ空文字。 */
    protected function shortDate(?string $ymd): string
    {
        if (!$ymd || !preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $ymd, $m)) {
            return '';
        }
        return (int)$m[2] . '/' . (int)$m[3];
    }

    /**
     * 部屋アイコンを円形にクロップして描画する。取得失敗時はプレースホルダの円を描く。
     */
    protected function drawIcon(\GdImage $im, ?string $iconUrl, int $x, int $y, int $size, int $fallbackCol): void
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
    protected function loadIcon(string $url): ?\GdImage
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
    protected function looksLikeImage(string $data): bool
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

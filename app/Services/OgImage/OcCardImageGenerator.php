<?php

declare(strict_types=1);

namespace App\Services\OgImage;

use App\Services\Crawler\FileDownloader;

/**
 * ルーム個別ページ用の動的OGP画像（1200x630 PNG）を GD で生成する。
 *
 * レイアウト: 左に部屋アイコン、その右に「ヘッダー(メンバー数＋7日増減) 1行」＋「部屋名 最大3行
 * （多言語・比例フォント・はみ出しは … で省略）」。下段に30日メンバー数スパークライン（両端に開始/
 * 終了日）。フッター左にサイト名、右にドメイン。ヘッダー文言・サイト名は表示ロケール(ja/tw/th)で翻訳。
 *
 * フォントは storage/font 同梱（本番/ローカルで同一描画）。1文字ずつ「その字を持つ最初のフォント」を
 * 選ぶ多言語フォールバック: 日本語/中国語/ラテン/数字=Noto CJK JP、タイ語=Noto Sans Thai、絵文字=
 * NotoColorEmoji のカラーPNG、その他の記号=NotoSansSymbols2。どれも無い字はスキップ（豆腐を出さない）。
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

    /** 太字テキスト用フォント（1文字ごとに先頭から cmap を持つ最初のフォントを採用＝多言語対応） */
    private string $fontBold;
    private string $fontMedium;
    /** @var string[] タイトル・増減用（Noto CJK JP → Thai の順で1文字ずつフォールバック） */
    private array $fontsBold;
    /** @var string[] ヘッダー・フッター用（同上の Regular） */
    private array $fontsMedium;
    private string $fontSymbol;
    private NotoColorEmojiReader $emoji;

    public function __construct(
        private FileDownloader $fileDownloader,
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

        // --- スパークライン（下段・先に描いて他要素を上に重ねる。最新ポイントに人数＋日付ラベル） ---
        $this->drawSparkline($im, $series, $dates, $member, $accent, $sub);

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

    /**
     * 1行テキストを多言語フォールバックで描画し、送り幅を返す（折り返し無し）。ヘッダー/増減/フッター用。
     * テキスト=フォントリスト（先頭から cmap を持つ最初）、絵文字=カラー、記号=モノクロ記号フォント。
     * imagettftext はベースライン基準なので、上端 $top から先頭フォントのアセントぶん下げて描く。
     *
     * @param string[] $fontList 1文字ずつ先頭から試すフォント（Noto CJK → Thai 等）
     */
    private function drawLine(\GdImage $im, string $text, int $x, int $top, int $size, int $color, array $fontList): int
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
    private function measureLine(string $text, int $size, array $fontList): int
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
    private function segmentText(string $text, array $fontList): array
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
    private function resolveFont(int $cp, array $fontList): ?string
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
    private function fontRanges(string $path): ?array
    {
        static $cache = [];
        if (array_key_exists($path, $cache)) {
            return $cache[$path];
        }
        return $cache[$path] = is_file($path) ? $this->readCmapRanges($path) : null;
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

    /**
     * $text の先頭から、送り幅が $maxWidth に収まる最長プレフィックスを「区切り可能位置」で返す。
     * 英数字が連続する“単語”の途中では切らない（前の区切りまで戻す）。日本語などは任意位置で切れる。
     * 区切り位置では1つも入らない場合は '' を返す（呼び出し側が改行）。ただし $allowCharBreak=true
     * （行頭で、1単語が1行より長い等）のときは文字単位で入るだけ入れる（最低1文字・無限ループ防止）。
     */
    private function fitPrefix(string $text, string $font, int $size, int $maxWidth, bool $allowCharBreak): string
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
    private function isWordChar(string $ch): bool
    {
        return $ch >= '0' && $ch <= 'z' && preg_match('/[A-Za-z0-9]/', $ch) === 1;
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

    /**
     * 30日メンバー数スパークライン（塗りつぶし付き折れ線）を下部に描画する。系列が2点未満なら描かない。
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
        // 下段(人数)を点のすぐ上に、上段(日付)をさらにその上へ積む（$top は各行の ink 上端）
        $valTop = $py - 16 - $valSize;
        $dateTop = $valTop - 4 - $dateSize;

        $anchor = fn(int $w): int => $rightAlign
            ? min($px + 12, self::WIDTH - 24) - $w   // 右揃え（右端の点）
            : max($px - 12, 24);                     // 左揃え（左端の点）
        if ($dateStr !== '') {
            $this->drawLine($im, $dateStr, $anchor($this->measureLine($dateStr, $dateSize, $this->fontsMedium)), $dateTop, $dateSize, $textCol, $this->fontsMedium);
        }
        $this->drawLine($im, $valStr, $anchor($this->measureLine($valStr, $valSize, $this->fontsMedium)), $valTop, $valSize, $textCol, $this->fontsMedium);
    }

    /** 'Y-m-d' を 'n/j'（例 6/3）へ。解釈できなければ空文字。 */
    private function shortDate(?string $ymd): string
    {
        if (!$ymd || !preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $ymd, $m)) {
            return '';
        }
        return (int)$m[2] . '/' . (int)$m[3];
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

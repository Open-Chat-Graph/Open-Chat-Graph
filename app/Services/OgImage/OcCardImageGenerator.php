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
 * 日本語描画には mgenplus（比例・M+由来）を storage/font 同梱で使う（本番/ローカルで同一描画）。
 * mgenplus はラテン・数字も含むので、数値・記号も同じフォントで描く。
 */
class OcCardImageGenerator
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;

    /** アイコン取得の総時間上限（秒・接続〜受信完了）。落ちてもプレースホルダで生成を続行する */
    private const ICON_MAX_DURATION = 3;

    /** アイコン取得の通信アイドル上限（秒） */
    private const ICON_IDLE_TIMEOUT = 2;

    /** アイコン取得用UA（クローラーと同一の OpenChatStatsbot 名義） */
    private const ICON_FETCH_UA = 'Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36 (compatible; OpenChatStatsbot; +https://github.com/Open-Chat-Graph/Open-Chat-Graph)';

    private string $fontBold;
    private string $fontMedium;

    public function __construct(
        private FileDownloader $fileDownloader,
    ) {
        $this->fontBold = __DIR__ . '/../../../storage/font/mgenplus-1c-bold.ttf';
        $this->fontMedium = __DIR__ . '/../../../storage/font/mgenplus-1p-medium.ttf';
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

        // --- タイトル: 部屋名（最大2行・折り返し・省略） ---
        $lines = $this->wrapLines($name, $this->fontBold, 50, $rightEdge - $rightX, 2);
        $titleTop = $headY + 30;
        $lineHeight = 66;
        foreach ($lines as $i => $line) {
            $this->drawText($im, $line, $rightX, $titleTop + $lineHeight * ($i + 1), $this->fontBold, 50, $white);
        }

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
     * テキストを幅 $maxWidth で文字単位に折り返し、最大 $maxLines 行に収める。
     * 収まらない場合は最終行の末尾を「…」で省略する。
     *
     * @return string[] 各行の文字列（最大 $maxLines 要素）
     */
    private function wrapLines(string $text, string $font, int $size, int $maxWidth, int $maxLines): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $lines = [];
        $cur = '';
        foreach ($chars as $c) {
            $cand = $cur . $c;
            if ($cur !== '' && $this->textWidth($cand, $font, $size) > $maxWidth) {
                $lines[] = $cur;
                $cur = $c;
            } else {
                $cur = $cand;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }

        if (count($lines) <= $maxLines) {
            return $lines;
        }

        // 溢れた: 先頭 maxLines 行だけ残し、最終行を「…」で省略する
        $kept = array_slice($lines, 0, $maxLines);
        $last = $kept[$maxLines - 1];
        while ($last !== '' && $this->textWidth($last . '…', $font, $size) > $maxWidth) {
            $last = mb_substr($last, 0, mb_strlen($last) - 1);
        }
        $kept[$maxLines - 1] = $last . '…';
        return $kept;
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

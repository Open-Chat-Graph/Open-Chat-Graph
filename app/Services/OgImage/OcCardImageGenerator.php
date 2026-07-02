<?php

declare(strict_types=1);

namespace App\Services\OgImage;

/**
 * ルーム個別ページ用の動的OGP画像（1200x630 PNG）を GD で生成する。
 *
 * シェア時のクリック率を上げるため、固定画像ではなく
 * 「部屋アイコン + 現在メンバー数 + 直近7日増減 + 30日メンバー数スパークライン」を焼き込む。
 * 部屋名はSNS側がog:titleとして画像の外に表示するため、画像内には文字焼きしない
 * （CJK/タイ文字フォントの同梱を避け、数字・記号のみの軽量フォントサブセットで全ロケール対応する）。
 *
 * フォント: fonts/DejaVuSans(-Bold)-subset.ttf（数字+基本ラテン+▲▼のみ約24KB。
 * DejaVu Fonts ライセンス（Bitstream Vera 派生・再配布可）に基づき同梱）。
 */
class OcCardImageGenerator
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;

    /** 部屋アイコンの取得の総時間上限（秒）。落ちてもプレースホルダで生成を続行する */
    private const ICON_FETCH_TIMEOUT = 3;

    /** 接続確立の上限（秒） */
    private const ICON_CONNECT_TIMEOUT = 2;

    /** アイコン取得の最大バイト数（これを超えたら中断） */
    private const ICON_MAX_BYTES = 3145728; // 3MB

    /** アイコン取得用UA（クローラーと同一の OpenChatStatsbot 名義） */
    private const ICON_FETCH_UA = 'Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36 (compatible; OpenChatStatsbot; +https://github.com/Open-Chat-Graph/Open-Chat-Graph)';

    private string $fontRegular;
    private string $fontBold;

    public function __construct()
    {
        $this->fontRegular = __DIR__ . '/fonts/DejaVuSans-subset.ttf';
        $this->fontBold = __DIR__ . '/fonts/DejaVuSans-Bold-subset.ttf';
    }

    /**
     * カード画像を生成してファイルへ保存する。
     *
     * @param string     $savePath   保存先（.png）。親ディレクトリは自動作成。一時ファイル経由でアトミックに置換
     * @param int        $member     現在メンバー数
     * @param int|null   $diffWeek   直近7日のメンバー増減（不明は null＝非表示）
     * @param int[]      $series     スパークライン用のメンバー数系列（日付昇順・30日程度、空なら非表示）
     * @param ?string    $iconUrl    部屋アイコンURL（取得失敗・null はプレースホルダ円）
     */
    public function generate(string $savePath, int $member, ?int $diffWeek, array $series, ?string $iconUrl): bool
    {
        // 描画能力を事前に control-flow で判定する（例外に頼らない）。
        // FreeType 関数(imagettftext)まで確認するのは、GD が FreeType 無しビルドだと
        // imagettftext が警告→（このアプリのハンドラで）例外になるため。
        // ここを通れば通常運用で render() は例外を投げない設計（外部アイコンの取得/デコードだけは
        // drawIcon 内で個別に扱う）。想定外の例外は握りつぶさず表に出す（バグを隠さない）。
        if (
            !function_exists('imagecreatetruecolor')
            || !function_exists('imagettftext')
            || !is_file($this->fontBold)
            || !is_file($this->fontRegular)
        ) {
            return false;
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
        $sub = imagecolorallocate($im, 150, 160, 185);
        $green = imagecolorallocate($im, 76, 217, 123);
        $red = imagecolorallocate($im, 240, 98, 98);
        $accent = imagecolorallocate($im, 88, 148, 255);

        // --- スパークライン（下半分・先に描いて他要素を上に重ねる） ---
        $this->drawSparkline($im, $series, $accent);

        // --- 部屋アイコン（左上・角丸風の円形クロップ） ---
        $iconSize = 200;
        $iconX = 80;
        $iconY = 80;
        $this->drawIcon($im, $iconUrl, $iconX, $iconY, $iconSize, $accent);

        // --- メンバー数（アイコン右・特大） ---
        $numText = number_format($member);
        $numSize = 110;
        // 桁が多い場合は縮める
        if (strlen($numText) > 7) $numSize = 88;
        $numX = $iconX + $iconSize + 70;
        $numY = 205;
        imagettftext($im, $numSize, 0, $numX, $numY, $white, $this->fontBold, $numText);

        // --- 直近7日増減（数値の下） ---
        if ($diffWeek !== null) {
            $isUp = $diffWeek >= 0;
            $arrow = $isUp ? '▲' : '▼';
            $diffCol = $isUp ? $green : $red;
            $diffText = $arrow . ' ' . ($isUp ? '+' : '') . number_format($diffWeek) . ' / 7d';
            imagettftext($im, 40, 0, $numX + 6, $numY + 80, $diffCol, $this->fontBold, $diffText);
        }

        // --- ブランドフッター（右下・ラテンのみ） ---
        $brand = 'openchat-review.me';
        $bs = 30;
        $bbox = imagettfbbox($bs, 0, $this->fontRegular, $brand);
        $bw = abs($bbox[4] - $bbox[0]);
        imagettftext($im, $bs, 0, self::WIDTH - $bw - 56, self::HEIGHT - 44, $sub, $this->fontRegular, $brand);

        // --- 保存（アトミック） ---
        // mkdir はレース時に警告→例外になるため、try/catch 済みの mkdirIfNotExists を使う。
        mkdirIfNotExists($dir = dirname($savePath));

        // tmp 名は pid で一意なので他プロセスと競合しない。imagepng/rename が失敗したときだけ
        // 自分の残骸を消す（成功時は rename 済みで tmp は無い）。例外は握りつぶさない。
        $tmp = $savePath . '.' . getmypid() . '.tmp';
        try {
            if (!imagepng($im, $tmp, 6)) {
                return false;
            }
            return rename($tmp, $savePath);
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }

    /**
     * 30日メンバー数スパークライン（塗りつぶし付き折れ線）を下部に描画する。
     * 系列が2点未満なら何も描かない。
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

        $left = 80;
        $right = self::WIDTH - 80;
        $topY = 380;
        $bottomY = self::HEIGHT - 90;

        $min = min($series);
        $max = max($series);
        $range = max(1, $max - $min);
        // ほぼ横ばいでも線が中央に見えるよう余白を取る
        $pad = (int)($range * 0.15);
        $min -= $pad;
        $range = max(1, $max - $min);

        $points = [];
        for ($i = 0; $i < $n; $i++) {
            $x = (int)($left + ($right - $left) * $i / ($n - 1));
            $y = (int)($bottomY - ($bottomY - $topY) * ($series[$i] - $min) / $range);
            $points[] = [$x, $y];
        }

        // 塗りつぶし（半透明風に暗いブルーで多角形を塗る）
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

        // 折れ線
        imagesetthickness($im, 4);
        for ($i = 1; $i < $n; $i++) {
            imageline($im, $points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1], $lineCol);
        }
        imagesetthickness($im, 1);

        // 終端ドット
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
            // プレースホルダ: アクセント色の円
            imagefilledellipse($im, $x + (int)($size / 2), $y + (int)($size / 2), $size, $size, $fallbackCol);
            return;
        }

        // 正方形へリサイズ
        $sq = imagecreatetruecolor($size, $size);
        imagecopyresampled($sq, $src, 0, 0, 0, 0, $size, $size, imagesx($src), imagesy($src));

        // 円形マスク: 円の外側を背景色で塗ってから転写（アンチエイリアスは簡易）
        $mask = imagecreatetruecolor($size, $size);
        $magic = imagecolorallocate($mask, 1, 2, 3);
        imagefill($mask, 0, 0, $magic);
        $clear = imagecolorallocate($mask, 255, 255, 255);
        imagefilledellipse($mask, (int)($size / 2), (int)($size / 2), $size, $size, $clear);

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
     * ここは「信頼できない外部入力」を扱う唯一の場所。取得失敗は control-flow（curl の戻り値・HTTP
     * ステータス・画像シグネチャ判定）で捌き、例外を握りつぶさない。デコードだけは、シグネチャが
     * 画像でも破損データだと imagecreatefromstring が警告→（このアプリのハンドラで）例外を投げうるため、
     * その一点に限って \ErrorException を受けて「壊れた外部画像＝想定内の入力エラー」として null に落とす。
     */
    private function loadIcon(string $url): ?\GdImage
    {
        $data = $this->fetchIcon($url);
        if ($data === null || !$this->looksLikeImage($data)) {
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

    /**
     * アイコン画像を取得する。接続・総転送・最大サイズすべてに curl の上限を掛け、失敗は戻り値で表す
     * （どんな失敗でも null）。curl が無い環境では取得しない（→プレースホルダ）。
     */
    private function fetchIcon(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::ICON_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::ICON_FETCH_TIMEOUT,   // 接続後も含めた総時間の上限（ワーカー拘束を厳密に制限）
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_USERAGENT => self::ICON_FETCH_UA,
            CURLOPT_BUFFERSIZE => 65536,
            // 受信量が上限を超えたら即中断（巨大レスポンスでメモリを食わせない）
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function ($ch, $dlTotal, $dlNow) {
                return $dlNow > self::ICON_MAX_BYTES ? 1 : 0;
            },
        ]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close() は PHP 8.0 以降 no-op、8.5 で deprecated。このアプリのハンドラは deprecation も
        // 例外化するため呼ばない（呼ぶと取得成功でも例外になりアイコンが消える。実際にこれで全部屋が
        // プレースホルダになった）。ハンドルはスクリプト終了時に解放される。
        if ($data === false || $data === '' || !is_string($data) || ($code !== 0 && $code >= 400)) {
            return null;
        }
        return $data;
    }
}

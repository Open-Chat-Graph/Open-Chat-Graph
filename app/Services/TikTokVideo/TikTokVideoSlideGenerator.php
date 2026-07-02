<?php

declare(strict_types=1);

namespace App\Services\TikTokVideo;

use App\Services\OgImage\GdTextRenderer;

/**
 * TikTok 用縦型動画（1080x1920）のスライド PNG を GD で生成する。
 *
 * OGP カード（OcCardImageGenerator）とトーンを揃えた濃紺グラデーション基調。
 * 多言語テキスト描画・円形アイコン等は基底クラス GdTextRenderer の共通エンジンを使う。
 * 文言は t()/sprintfT() でロケール（ja/tw/th）翻訳されるため、urlRoot を切り替えるだけで
 * 台湾・タイ向けのスライドも生成できる。
 *
 * スライドは静止画で、動き（ズーム・トランジション）は TikTokVideoRenderer が ffmpeg で付ける。
 */
class TikTokVideoSlideGenerator extends GdTextRenderer
{
    public const WIDTH = 1080;
    public const HEIGHT = 1920;

    /** @var array{0:int,1:int,2:int} 背景グラデーション上端色（OGPカードと同系統の濃紺） */
    private const BG_TOP = [24, 32, 54];
    private const BG_BOTTOM = [8, 12, 24];

    /**
     * タイトルスライド:「今日伸びたオープンチャット TOP5」＋日付。
     *
     * @param string $dateLabel 例 '7/2'（ロケール非依存の数字表記）
     * @param int    $roomCount ランキング件数（TOP N の N）
     */
    public function renderTitleSlide(string $dateLabel, int $roomCount): ?string
    {
        $im = $this->createCanvas();
        if ($im === null) {
            return null;
        }
        $c = $this->colors($im);

        // 上部: サイト名（ロケール翻訳）
        $brand = t('オプチャグラフ');
        $this->drawCenteredLine($im, $brand, 150, 40, $c['sub'], $this->fontsMedium);

        // 中央: 日付 → メインタイトル → TOP N
        $this->drawCenteredLine($im, $dateLabel, 480, 84, $c['white'], $this->fontsBold);
        $this->drawTitle($im, t('今日伸びたオープンチャット'), 80, 640, self::WIDTH - 160, 70, 3, $c['white'], 'center');
        $this->drawCenteredLine($im, 'TOP ' . $roomCount, 950, 170, $c['accent'], $this->fontsBold);

        // 下部: ドメイン
        $this->drawCenteredLine($im, 'openchat-review.me', self::HEIGHT - 160, 32, $c['sub'], $this->fontsMedium);

        return $this->toPng($im);
    }

    /**
     * ルーム1件のスライド: 順位・アイコン・部屋名・メンバー数・24時間増加数・30日グラフ。
     *
     * @param int         $rank      順位（1始まり）
     * @param string      $name      部屋名（多言語）
     * @param int         $member    現在メンバー数
     * @param int         $increase  24時間のメンバー増加数
     * @param float|null  $percent   増加率(%)。null は非表示
     * @param ?string     $iconUrl   アイコンURL（取得失敗はプレースホルダ円）
     * @param array<int|null> $series 30日メンバー数系列（昇順・null=欠測）
     * @param string[]    $dates     $series と同じ並びの日付(Y-m-d)
     * @param string      $dateLabel 右上に添える日付 例 '7/2'
     */
    public function renderRoomSlide(
        int $rank,
        string $name,
        int $member,
        int $increase,
        ?float $percent,
        ?string $iconUrl,
        array $series,
        array $dates,
        string $dateLabel,
    ): ?string {
        $im = $this->createCanvas();
        if ($im === null) {
            return null;
        }
        $c = $this->colors($im);

        // 左上: 順位（ロケール翻訳: ja「1位」/ tw「第1名」/ th「อันดับ 1」）
        $rankLabel = sprintfT('%s位', (string)$rank);
        $this->drawLine($im, $rankLabel, 80, 90, 96, $c['accent'], $this->fontsBold);

        // 右上: 日付（控えめ）
        $dw = $this->measureLine($dateLabel, 36, $this->fontsMedium);
        $this->drawLine($im, $dateLabel, self::WIDTH - 80 - $dw, 120, 36, $c['sub'], $this->fontsMedium);

        // アイコン（中央・円形 360px）
        $iconSize = 360;
        $this->drawIcon($im, $iconUrl, intdiv(self::WIDTH - $iconSize, 2), 300, $iconSize, $c['accent']);

        // 部屋名（中央寄せ・最大2行・自動縮小）
        $this->drawTitle($im, $name, 90, 730, self::WIDTH - 180, 56, 2, $c['white'], 'center');

        // メンバー数（muted）
        $this->drawCenteredLine($im, sprintfT('メンバー %s人', number_format($member)), 930, 40, $c['sub'], $this->fontsMedium);

        // 24時間の増加数（このスライドの主役。緑の大きな数字）
        $this->drawCenteredLine($im, t('24時間の増加数'), 1030, 34, $c['sub'], $this->fontsMedium);
        $isUp = $increase >= 0;
        $incText = ($isUp ? '+' : '') . number_format($increase);
        $this->drawCenteredLine($im, $incText, 1085, 128, $isUp ? $c['green'] : $c['red'], $this->fontsBold);
        if ($percent !== null) {
            $pctText = ($percent >= 0 ? '+' : '') . number_format($percent, 1) . '%';
            $this->drawCenteredLine($im, $pctText, 1270, 44, $isUp ? $c['green'] : $c['red'], $this->fontsBold);
        }

        // 30日グラフ（下段）
        $this->drawSparklineRect($im, $series, $dates, 120, self::WIDTH - 120, 1400, 1680, $c['accent'], $c['sub'], $c['fill']);

        // フッター: サイト名＋ドメイン
        $this->drawCenteredLine($im, t('オプチャグラフ') . '  |  openchat-review.me', self::HEIGHT - 130, 30, $c['sub'], $this->fontsMedium);

        return $this->toPng($im);
    }

    /**
     * 締めスライド: サイト名 →「毎日ランキング更新中」→ 検索誘導 → ドメイン。
     * TikTok はリンク導線が弱いため「ブランド名で検索させる」CTA を主にする。
     */
    public function renderOutroSlide(): ?string
    {
        $im = $this->createCanvas();
        if ($im === null) {
            return null;
        }
        $c = $this->colors($im);

        $brand = t('オプチャグラフ');
        $this->drawTitle($im, $brand, 80, 680, self::WIDTH - 160, 84, 2, $c['white'], 'center');
        $this->drawCenteredLine($im, t('毎日ランキング更新中'), 900, 44, $c['sub'], $this->fontsMedium);
        $this->drawCenteredLine($im, '🔍 ' . sprintfT('「%s」で検索', $brand), 1060, 46, $c['accent'], $this->fontsBold);
        $this->drawCenteredLine($im, 'openchat-review.me', 1220, 36, $c['sub'], $this->fontsMedium);

        return $this->toPng($im);
    }

    /** 濃紺グラデーションのキャンバスを作る。GD/フォントが使えない環境では null。 */
    private function createCanvas(): ?\GdImage
    {
        if (!$this->canRenderText()) {
            return null;
        }
        $im = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $t = $y / self::HEIGHT;
            $col = imagecolorallocate(
                $im,
                (int)(self::BG_TOP[0] + (self::BG_BOTTOM[0] - self::BG_TOP[0]) * $t),
                (int)(self::BG_TOP[1] + (self::BG_BOTTOM[1] - self::BG_TOP[1]) * $t),
                (int)(self::BG_TOP[2] + (self::BG_BOTTOM[2] - self::BG_TOP[2]) * $t),
            );
            imageline($im, 0, $y, self::WIDTH, $y, $col);
        }
        return $im;
    }

    /** 共通パレット（OGPカードと同色） @return array<string,int> */
    private function colors(\GdImage $im): array
    {
        return [
            'white' => imagecolorallocate($im, 245, 247, 252),
            'sub' => imagecolorallocate($im, 150, 162, 190),
            'green' => imagecolorallocate($im, 76, 217, 123),
            'red' => imagecolorallocate($im, 240, 98, 98),
            'accent' => imagecolorallocate($im, 88, 148, 255),
            'fill' => imagecolorallocate($im, 26, 44, 82),
        ];
    }

    /** 1行テキストをキャンバス水平中央に描く（折り返し無し・はみ出す長文は drawTitle を使うこと） */
    private function drawCenteredLine(\GdImage $im, string $text, int $top, int $size, int $color, array $fontList): void
    {
        $w = $this->measureLine($text, $size, $fontList);
        $this->drawLine($im, $text, max(0, intdiv(self::WIDTH - $w, 2)), $top, $size, $color, $fontList);
    }

    /**
     * 指定矩形に 30日メンバー数の折れ線（塗りつぶし付き）を描く。系列2点未満は描かない。
     * OcCardImageGenerator::drawSparkline と同じ描法を矩形パラメータ化したもの。
     *
     * @param array<int|null> $series
     * @param string[] $dates
     */
    private function drawSparklineRect(\GdImage $im, array $series, array $dates, int $left, int $right, int $topY, int $bottomY, int $lineCol, int $labelCol, int $fillCol): void
    {
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

        $poly = [];
        foreach ($points as [$x, $y]) {
            $poly[] = $x;
            $poly[] = $y;
        }
        $poly[] = $right;
        $poly[] = $bottomY;
        $poly[] = $left;
        $poly[] = $bottomY;
        imagefilledpolygon($im, $poly, $fillCol);

        imagesetthickness($im, 6);
        for ($i = 1; $i < $n; $i++) {
            imageline($im, $points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1], $lineCol);
        }
        imagesetthickness($im, 1);

        [$ex, $ey] = $points[$n - 1];
        imagefilledellipse($im, $ex, $ey, 22, 22, $lineCol);

        // 両端の日付(M/D)
        $startLabel = $this->shortDate($pairs[0][1]);
        $endLabel = $this->shortDate($pairs[$n - 1][1]);
        $ly = $bottomY + 16;
        if ($startLabel !== '') {
            $this->drawLine($im, $startLabel, $left, $ly, 28, $labelCol, $this->fontsMedium);
        }
        if ($endLabel !== '') {
            $ew = $this->measureLine($endLabel, 28, $this->fontsMedium);
            $this->drawLine($im, $endLabel, $right - $ew, $ly, 28, $labelCol, $this->fontsMedium);
        }
    }

    /** PNG バイト列にして返す */
    private function toPng(\GdImage $im): ?string
    {
        ob_start();
        imagepng($im, null, 6);
        return ob_get_clean() ?: null;
    }
}

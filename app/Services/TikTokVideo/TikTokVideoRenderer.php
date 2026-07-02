<?php

declare(strict_types=1);

namespace App\Services\TikTokVideo;

/**
 * スライド PNG 群を ffmpeg で 1080x1920 / 30fps の縦型 mp4 に合成する。
 *
 * 演出はテンプレート固定:
 *  - 各スライドに Ken Burns 効果（ゆっくりズームイン/アウトを交互に）
 *  - スライド間は xfade（タイトル/締めは fade、ルーム間は slideleft）
 *  - 音声は付けない（-an）。Phase 1 は TikTok アプリ内で投稿時にトレンド楽曲を付ける方が
 *    リーチが伸びるため意図的に無音。API 直接投稿(Phase 3)に進む場合はフリー BGM 合成を足す。
 *
 * ffmpeg は実行環境（GitHub Actions ubuntu-latest / ローカル）にインストール済みであることが前提。
 * 本番共用サーバーでは動かさない設計（レンダリングは GitHub Actions 側で行う）。
 */
class TikTokVideoRenderer
{
    private const FPS = 30;

    /** スライド間クロスフェード秒数 */
    private const FADE_SEC = 0.45;

    /** Ken Burns の1フレームあたりズーム量（30fps で 3.2秒 ≈ 1.0 → 1.09） */
    private const ZOOM_STEP = 0.0009;
    private const ZOOM_MAX = 1.12;

    /**
     * @param array<int,array{path:string,duration:float,transition?:string}> $slides
     *        表示順のスライド。duration は表示秒数、transition は「前のスライドからの」
     *        トランジション（fade / slideleft 等の xfade transition 名。先頭では無視）
     * @param string $outPath 出力 mp4 パス
     * @return bool 成功したか（失敗時は stderr ログを $errorOutput に格納）
     */
    public function render(array $slides, string $outPath, ?string &$errorOutput = null): bool
    {
        if (count($slides) < 2) {
            $errorOutput = 'スライドが2枚未満です';
            return false;
        }
        foreach ($slides as $s) {
            if (!is_file($s['path'])) {
                $errorOutput = "スライドがありません: {$s['path']}";
                return false;
            }
        }

        $cmd = $this->buildCommand($slides, $outPath);
        exec($cmd . ' 2>&1', $output, $code);
        $errorOutput = implode("\n", $output);

        return $code === 0 && is_file($outPath) && filesize($outPath) > 0;
    }

    /**
     * ffmpeg コマンドを組み立てる（テスト用に public）。
     *
     * フィルタ構成:
     *   [k:v] scale(2倍に拡大しズームのジッタを抑制) → zoompan(Ken Burns) → [vk]
     *   [v0][v1] xfade → [x1] ... 最後に format=yuv420p
     * xfade の offset は「先頭からの累積表示時間 - フェード累積」で求める。
     *
     * @param array<int,array{path:string,duration:float,transition?:string}> $slides
     */
    public function buildCommand(array $slides, string $outPath): string
    {
        $inputs = [];
        $filters = [];
        $n = count($slides);

        foreach ($slides as $i => $s) {
            $inputs[] = '-i ' . escapeshellarg($s['path']);
            $frames = (int)round($s['duration'] * self::FPS);
            // 偶数スライドはズームイン、奇数はズームアウト（単調さを避ける）
            $zoomExpr = $i % 2 === 0
                ? sprintf("min(1+%.4f*on,%.2f)", self::ZOOM_STEP, self::ZOOM_MAX)
                : sprintf("max(%.2f-%.4f*on,1.0)", self::ZOOM_MAX, self::ZOOM_STEP);
            $filters[] = sprintf(
                "[%d:v]scale=%d:%d,zoompan=z='%s':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=%d:s=%dx%d:fps=%d[v%d]",
                $i,
                TikTokVideoSlideGenerator::WIDTH * 2,
                TikTokVideoSlideGenerator::HEIGHT * 2,
                $zoomExpr,
                $frames,
                TikTokVideoSlideGenerator::WIDTH,
                TikTokVideoSlideGenerator::HEIGHT,
                self::FPS,
                $i,
            );
        }

        // xfade チェーン: offset_k = (先頭から k 枚目までの表示時間合計) - k * FADE_SEC
        $offset = 0.0;
        $prevLabel = 'v0';
        for ($i = 1; $i < $n; $i++) {
            $offset += $slides[$i - 1]['duration'] - self::FADE_SEC;
            $transition = $slides[$i]['transition'] ?? 'fade';
            $outLabel = $i === $n - 1 ? 'vout' : "x{$i}";
            $filters[] = sprintf(
                '[%s][v%d]xfade=transition=%s:duration=%.2f:offset=%.2f[%s]',
                $prevLabel,
                $i,
                $transition,
                self::FADE_SEC,
                $offset,
                $outLabel,
            );
            $prevLabel = $outLabel;
        }
        $filters[] = '[vout]format=yuv420p[v]';

        return implode(' ', [
            'ffmpeg -y',
            implode(' ', $inputs),
            '-filter_complex ' . escapeshellarg(implode(';', $filters)),
            '-map ' . escapeshellarg('[v]'),
            '-an',
            '-c:v libx264 -preset medium -crf 26 -r ' . self::FPS,
            '-movflags +faststart',
            escapeshellarg($outPath),
        ]);
    }
}

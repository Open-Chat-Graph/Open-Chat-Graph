<?php

declare(strict_types=1);

namespace App\Services\TikTokVideo;

/**
 * スライド PNG 群を ffmpeg で 1080x1920 / 30fps の縦型 mp4 に合成する。
 *
 * 演出はテンプレート固定:
 *  - 各スライドに Ken Burns 効果（ゆっくりズームイン/アウトを交互に）
 *  - スライド間は xfade（タイトル/締めは fade、ルーム間は slideleft）
 *  - スライドに 'audio'（WAV パス）があれば、そのスライドが完全に表示されたタイミングから
 *    再生されるようミックスする（ずんだもんナレーション用）。無ければ無音（-an）
 *
 * BGM は付けない: Phase 1 は TikTok アプリ内で投稿時にトレンド楽曲を付ける方がリーチが伸びるため
 * （ナレーションとアプリ内楽曲は共存できる）。
 *
 * ffmpeg は実行環境（GitHub Actions ubuntu-latest / ローカル）にインストール済みであることが前提。
 * 本番共用サーバーでは動かさない設計（レンダリングは GitHub Actions 側で行う）。
 */
class TikTokVideoRenderer
{
    private const FPS = 30;

    /** スライド間クロスフェード秒数（TikTokRisingVideoService の表示時間計算とも共有） */
    public const FADE_SEC = 0.45;

    /** Ken Burns の1フレームあたりズーム量（30fps で 3.2秒 ≈ 1.0 → 1.09） */
    private const ZOOM_STEP = 0.0009;
    private const ZOOM_MAX = 1.12;

    /**
     * @param array<int,array{path:string,duration:float,transition?:string,audio?:string}> $slides
     *        表示順のスライド。duration は表示秒数、transition は「前のスライドからの」
     *        トランジション（fade / slideleft 等の xfade transition 名。先頭では無視）、
     *        audio はそのスライドで再生する WAV パス（省略可）
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
            if (isset($s['audio']) && !is_file($s['audio'])) {
                $errorOutput = "音声がありません: {$s['audio']}";
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
     *   音声: 各 WAV を adelay（スライドが完全表示になる時刻）→ amix
     * xfade の offset は「先頭からの累積表示時間 - フェード累積」で求める。
     *
     * @param array<int,array{path:string,duration:float,transition?:string,audio?:string}> $slides
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

        // 各スライドの「タイムライン上の開始時刻」（xfade で前のスライドと重なり始める時刻）。
        // startAt[k] = startAt[k-1] + duration[k-1] - FADE
        $startAt = [0.0];
        for ($i = 1; $i < $n; $i++) {
            $startAt[$i] = $startAt[$i - 1] + $slides[$i - 1]['duration'] - self::FADE_SEC;
        }

        // xfade チェーン
        $prevLabel = 'v0';
        for ($i = 1; $i < $n; $i++) {
            $transition = $slides[$i]['transition'] ?? 'fade';
            $outLabel = $i === $n - 1 ? 'vout' : "x{$i}";
            $filters[] = sprintf(
                '[%s][v%d]xfade=transition=%s:duration=%.2f:offset=%.2f[%s]',
                $prevLabel,
                $i,
                $transition,
                self::FADE_SEC,
                $startAt[$i],
                $outLabel,
            );
            $prevLabel = $outLabel;
        }
        $filters[] = '[vout]format=yuv420p[v]';

        // 音声: スライドが完全に表示になった時刻（startAt + FADE。先頭のみ少し置いて 0.3s）から再生
        $audioLabels = [];
        $inputIndex = $n;
        foreach ($slides as $i => $s) {
            if (!isset($s['audio'])) {
                continue;
            }
            $inputs[] = '-i ' . escapeshellarg($s['audio']);
            $delayMs = (int)round(($i === 0 ? 0.3 : $startAt[$i] + self::FADE_SEC) * 1000);
            $label = 'a' . count($audioLabels);
            $filters[] = sprintf('[%d:a]adelay=%d:all=1[%s]', $inputIndex, $delayMs, $label);
            $audioLabels[] = "[{$label}]";
            $inputIndex++;
        }

        $audioArgs = '-an';
        if ($audioLabels) {
            if (count($audioLabels) === 1) {
                $filters[] = "{$audioLabels[0]}anull[aout]";
            } else {
                // normalize=0: ナレーションは時間的に重ならないため音量を割らない
                $filters[] = implode('', $audioLabels) . 'amix=inputs=' . count($audioLabels) . ':normalize=0[aout]';
            }
            $audioArgs = '-map ' . escapeshellarg('[aout]') . ' -c:a aac -b:a 96k';
        }

        return implode(' ', [
            'ffmpeg -y',
            implode(' ', $inputs),
            '-filter_complex ' . escapeshellarg(implode(';', $filters)),
            '-map ' . escapeshellarg('[v]'),
            $audioArgs,
            '-c:v libx264 -preset medium -crf 26 -r ' . self::FPS,
            '-movflags +faststart',
            escapeshellarg($outPath),
        ]);
    }
}

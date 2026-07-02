<?php

declare(strict_types=1);

namespace App\Services\TikTokVideo;

use Shared\MimimalCmsConfig;

/**
 * ペイロード（デイリー急上昇ランキング）から TikTok 用縦型動画一式を生成する。
 *
 * 入力はプレーンな配列（本番 cron が TikTokVideoPayloadBuilder で作り GitHub repository_dispatch の
 * client_payload として送るのと同じ形。DB には触らないので GitHub Actions / ローカルどこでも動く）:
 *
 *   [
 *     'version'     => 1,
 *     'urlRoot'     => '' | '/tw' | '/th',
 *     'generatedAt' => 'Y-m-d H:i:s',
 *     'listType'    => 'daily',
 *     'rooms'       => [
 *       ['id'=>int,'name'=>string,'member'=>int,'increase'=>int,'percent'=>float|null,
 *        'iconUrl'=>string|null,'dates'=>string[],'series'=>(int|null)[]],
 *       ... 順位順
 *     ],
 *   ]
 *
 * 出力（$outDir 配下）:
 *   - tiktok_{listType}{lang}.mp4  … 動画本体（無音。投稿時にアプリ内で楽曲を付ける想定）
 *   - caption.txt                  … 投稿キャプション（コピペ用）
 *   - slides/*.png                 … 各スライド（サムネ選択・デバッグ用）
 */
class TikTokRisingVideoService
{
    private const TITLE_DURATION = 2.0;
    private const ROOM_DURATION = 3.2;
    private const OUTRO_DURATION = 2.4;

    public function __construct(
        private TikTokVideoSlideGenerator $slideGenerator,
        private TikTokVideoRenderer $renderer,
    ) {}

    /**
     * 動画を生成し、生成物のパスを返す。
     *
     * @param array<string,mixed> $payload
     * @return array{video:string,caption:string,slides:string[]} 生成したファイルパス
     * @throws \RuntimeException ペイロード不正・スライド生成不可・ffmpeg 失敗
     */
    public function generate(array $payload, string $outDir): array
    {
        $rooms = $this->validate($payload);

        // ペイロードのロケールで翻訳を効かせる（t()/sprintfT() は urlRoot を見る）
        MimimalCmsConfig::$urlRoot = $payload['urlRoot'];

        $slidesDir = $outDir . '/slides';
        if (!is_dir($slidesDir) && !mkdir($slidesDir, 0777, true)) {
            throw new \RuntimeException("出力ディレクトリを作成できません: {$slidesDir}");
        }

        $dateLabel = $this->dateLabel($payload['generatedAt']);

        // --- スライド生成（タイトル → 5位..1位のカウントダウン → 締め） ---
        $slideFiles = [];
        $slides = [];

        $png = $this->slideGenerator->renderTitleSlide($dateLabel, count($rooms));
        $slides[] = ['path' => $this->writeSlide($slidesDir, '00_title', $png), 'duration' => self::TITLE_DURATION];

        foreach (array_reverse($rooms, true) as $i => $room) {
            $rank = $i + 1;
            $png = $this->slideGenerator->renderRoomSlide(
                $rank,
                (string)$room['name'],
                (int)$room['member'],
                (int)$room['increase'],
                isset($room['percent']) ? (float)$room['percent'] : null,
                $room['iconUrl'] ?? null,
                $room['series'] ?? [],
                $room['dates'] ?? [],
                $dateLabel,
            );
            $slides[] = [
                'path' => $this->writeSlide($slidesDir, sprintf('%02d_rank%d', count($slides), $rank), $png),
                'duration' => self::ROOM_DURATION,
                'transition' => 'slideleft',
            ];
        }

        $png = $this->slideGenerator->renderOutroSlide();
        $slides[] = ['path' => $this->writeSlide($slidesDir, sprintf('%02d_outro', count($slides)), $png), 'duration' => self::OUTRO_DURATION];

        $slideFiles = array_column($slides, 'path');

        // --- ffmpeg 合成 ---
        $lang = str_replace('/', '_', (string)$payload['urlRoot']); // '' | '_tw' | '_th'
        $videoPath = $outDir . '/tiktok_' . $payload['listType'] . $lang . '.mp4';
        $error = null;
        if (!$this->renderer->render($slides, $videoPath, $error)) {
            throw new \RuntimeException("ffmpeg での動画合成に失敗しました:\n" . $error);
        }

        // --- キャプション ---
        $captionPath = $outDir . '/caption.txt';
        file_put_contents($captionPath, $this->buildCaption($rooms, $dateLabel));

        return ['video' => $videoPath, 'caption' => $captionPath, 'slides' => $slideFiles];
    }

    /**
     * ペイロードを検証し rooms（順位順・添字0=1位）を返す。
     *
     * @param array<string,mixed> $payload
     * @return array<int,array<string,mixed>>
     */
    private function validate(array $payload): array
    {
        if (($payload['version'] ?? null) !== 1) {
            throw new \RuntimeException('ペイロード version が不正です');
        }
        if (!in_array($payload['urlRoot'] ?? null, ['', '/tw', '/th'], true)) {
            throw new \RuntimeException('ペイロード urlRoot が不正です');
        }
        if (!is_string($payload['listType'] ?? null) || !preg_match('/^[a-z]+$/', $payload['listType'])) {
            throw new \RuntimeException('ペイロード listType が不正です');
        }
        $rooms = $payload['rooms'] ?? null;
        if (!is_array($rooms) || count($rooms) < 1) {
            throw new \RuntimeException('ペイロード rooms が空です');
        }
        foreach ($rooms as $room) {
            if (!isset($room['name'], $room['member'], $room['increase'])) {
                throw new \RuntimeException('ペイロード rooms の要素に name/member/increase がありません');
            }
        }
        return array_values($rooms);
    }

    /** 'Y-m-d H:i:s' → 'n/j'（動画内の日付表記・ロケール非依存の数字） */
    private function dateLabel(mixed $generatedAt): string
    {
        $ts = is_string($generatedAt) ? strtotime($generatedAt) : false;
        return $ts === false ? date('n/j') : date('n/j', $ts);
    }

    /**
     * 投稿キャプション（コピペ用）。1行目にタイトル、続けて上位の部屋名、末尾にハッシュタグ。
     *
     * @param array<int,array<string,mixed>> $rooms
     */
    private function buildCaption(array $rooms, string $dateLabel): string
    {
        $lines = [sprintf('%s %s TOP%d', $dateLabel, t('今日伸びたオープンチャット'), count($rooms))];
        foreach ($rooms as $i => $room) {
            $lines[] = sprintf(
                '%s %s (+%s)',
                sprintfT('%s位', (string)($i + 1)),
                mb_strimwidth((string)$room['name'], 0, 40, '…'),
                number_format((int)$room['increase']),
            );
        }
        $lines[] = '';
        $lines[] = t('#LINEオープンチャット #オープンチャット #オプチャ');
        return implode("\n", $lines) . "\n";
    }

    /** スライド PNG を書き出してパスを返す（生成不可環境は例外） */
    private function writeSlide(string $dir, string $name, ?string $png): string
    {
        if ($png === null) {
            throw new \RuntimeException('スライドを生成できません（GD/FreeType/フォントを確認してください）');
        }
        $path = "{$dir}/{$name}.png";
        if (file_put_contents($path, $png) === false) {
            throw new \RuntimeException("スライドを書き込めません: {$path}");
        }
        return $path;
    }
}

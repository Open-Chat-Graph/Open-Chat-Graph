<?php

declare(strict_types=1);

namespace App\Services\TikTokVideo;

/**
 * TikTok 動画のナレーション台本（ずんだもん口調・日本語）をペイロードから組み立てる。
 *
 * スライドと1:1対応の文章リストを返す（タイトル → 5位..1位 → 締め）。
 * ずんだもんは日本語音声なので日本（urlRoot=''）専用。台湾・タイ展開時は各言語の
 * TTS を別途検討する（現状は無音のまま）。
 */
class TikTokNarrationBuilder
{
    /** 読み上げ用の部屋名の最大文字数（長すぎるとテンポが崩れる。表示は省略なしなので読みだけ短縮） */
    private const NAME_MAX_CHARS = 14;

    /**
     * スライド順のナレーション文を返す。キーは TikTokRisingVideoService のスライド並びと同じ
     * （0=タイトル, 1..n=ルーム（下位→1位）, 最後=締め）。
     *
     * @param array<int,array<string,mixed>> $rooms 順位順（添字0 = 1位）
     * @return string[]
     */
    public function build(array $rooms, string $generatedAt): array
    {
        $ts = strtotime($generatedAt) ?: time();
        $lines = [sprintf(
            '%d月%d日、今日伸びたオープンチャット、トップ%dなのだ！',
            (int)date('n', $ts),
            (int)date('j', $ts),
            count($rooms),
        )];

        // ショート動画はテンポ優先で1文を短く（増加数は「プラスN人」・24時間の説明は画面表示に任せる）
        foreach (array_reverse($rooms, true) as $i => $room) {
            $rank = $i + 1;
            $name = $this->readableName((string)$room['name']);
            $increase = number_format((int)$room['increase']);
            if ($rank === 1) {
                $lines[] = "第1位は、{$name}！なんとプラス{$increase}人なのだ！";
            } else {
                $lines[] = "第{$rank}位、{$name}。プラス{$increase}人なのだ。";
            }
        }

        $lines[] = 'ランキングは毎日更新なのだ。オプチャグラフで検索なのだ！';
        return $lines;
    }

    /**
     * 部屋名を読み上げ向けに整形する。絵文字・装飾記号を落とし、長すぎる名前は切り詰める
     * （TTS が記号を変に読んだり、1室だけ異様に長くなるのを防ぐ）。空になったら汎用表現。
     */
    private function readableName(string $name): string
    {
        // 文字・数字・空白と、読みに影響しない日本語句読点類だけ残す
        $clean = preg_replace('/[^\p{L}\p{N}\p{Zs}ー〜！？。、・]/u', '', $name) ?? '';
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');
        if ($clean === '') {
            return 'こちらのお部屋';
        }
        if (mb_strlen($clean) > self::NAME_MAX_CHARS) {
            $clean = mb_substr($clean, 0, self::NAME_MAX_CHARS);
        }
        return $clean;
    }
}

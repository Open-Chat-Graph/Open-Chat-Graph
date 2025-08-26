<?php

declare(strict_types=1);

namespace App\Services\Recommend\TagDefinition\Ja;

use Shared\MimimalCmsConfig;

class RecommendUtility
{
    const OmitPettern = [
        'ディズニー ツムツム' => 'ツムツム',
        'Pokémon TCG Pocket' => 'ポケポケ',
        'Pokémon Champions' => 'ポケモンチャンピオンズ',
        '歌リレー' => 'ボイメ 歌',
        '歌い手のトークルーム' => '歌い手',
        'ゼベワン' => 'ZB1',
        'スプラトゥーン' => 'スプラ',
        '妖怪ウォッチ ぷにぷに' => 'ぷにぷに',
        'ONE PIECE バウンティラッシュ' => 'バウンティ',
        '中学生・高校生限定' => '中高生限定',
        '小学生・中学生限定' => '小中学生限定',
        '葬送のフリーレン' => 'フリーレン',
        'クーポン・無料配布' => 'クーポン',
        '片目界隈・自撮り界隈' => '自撮り界隈',
        '東方Project' => '東方',
        'ツイステッドワンダーランド' => 'ツイステ',
        'ハイキュー!!' => 'ハイキュー',
        'プロスピA' => 'プロスピ',
        'ビーファースト' => 'BE:FIRST',
        '馬場豊' => 'もこう',
        'ウィプレー' => 'WePlay',
        'エンハイプン' => 'ENHYPEN',
        'ブルーム' => '8LOOM',
        'ルセラ' => 'LE SSERAFIM',
        'ミセスグリーンアップル' => 'ミセス',
    ];

    const AdEnhancementTag = [
        '下ネタ',
        '競馬予想',
        '競艇予想',
        '仮想通貨',
        'Coin',
        '株式投資',
        'クーポン・無料配布',
        '美容整形',
        '億り人',
        'メルカリ',
        'FX',
        'パチンコ・スロット（パチスロ）',
        '副業',
        'せどり',
        'お金',
        'TEMU',
        '40代',
        '50代',
        '70代',
        '60代',
        '不動産',
        '企業研究',
        "新歓",
        "新入生",
        "オフ会",
        "28卒",
        "27卒",
        "26卒",
        "25卒",
        "24卒",
        "23卒",
        '営業',
        '起業',
        '金融',
        '就活',
        '人事',
        'セミナー',
        '占い師',
        '占い',
        '大人',
        'スタバ',
        'その先',
        '貯金',
        '節約',
        'TikTok Lite',
        '即承認',
        'ポケモンカード（ポケカ）',
        'トレーディングカード（トレカ）',
        'ワンピースカード',
        '投資',
        "ふるさと納税",
        'SHEIN',
        'ポイ活',
        '資産運用',
        'Instagram（インスタ）',
        'SNS',
        '全国 雑談',
        '不用品・遺品整理・汚部屋・ゴミ屋敷',
        'ZB1（ゼロベースワン／ゼベワン）',
        'Stray Kids',
        'IVE',
    ];

    static function extractTag(string|int $str): string
    {
        if (MimimalCmsConfig::$urlRoot !== '') {
            return (string)$str;
        }

        $str = (string)$str;

        // 文末の括弧内のテキストを抽出する正規表現
        if (preg_match('/（(.*)）$/', $str, $matches)) {
            // 括弧内のテキストが見つかった場合
            $textInsideParentheses = $matches[1];

            // 「／」で分割し、最後の要素を取得
            $parts = explode('／', $textInsideParentheses);
            $str = array_pop($parts);
        }

        return self::OmitPettern[$str] ?? $str;
    }

    static function getValidTag(string|int $str): string|false
    {
        $lowercaseTag = strtolower((string)$str);
        foreach (self::OmitPettern as $key => $originalTag) {
            if (strtolower($key) === $lowercaseTag) {
                return $originalTag;
            }
        }
        return false;
    }

    static function isAdEnhancementTag(string $tag): bool
    {
        return in_array($tag, self::AdEnhancementTag, true);
    }
}

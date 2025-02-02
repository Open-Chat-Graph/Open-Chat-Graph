<?php

declare(strict_types=1);

namespace App\Services\Recommend\TagDefinition\Ja;

use Shared\MimimalCmsConfig;

class RecommendUtility
{
    const OmitPettern = [
        'ディズニー ツムツム' => 'ツムツム',
        'Pokémon TCG Pocket' => 'ポケポケ',
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
}

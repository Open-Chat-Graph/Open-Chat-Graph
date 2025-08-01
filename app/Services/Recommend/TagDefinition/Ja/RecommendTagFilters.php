<?php

declare(strict_types=1);

namespace App\Services\Recommend\TagDefinition\Ja;

use Shared\MimimalCmsConfig;

class RecommendTagFilters
{
    const RecommendPageTagFilter = [];

    const FilteredTagSort = [];

    private const TopPageTagFilter = [];

    static function getTopPageTagFilter(): array
    {
        if (MimimalCmsConfig::$urlRoot !== '') {
            return [];
        }

        return array_merge(self::RecommendPageTagFilter, self::TopPageTagFilter);
    }

    const RedirectTags = [
        'ChatGPT' => '生成AI・ChatGPT',
        'AI画像・イラスト生成' => '画像生成AI・AIイラスト',
        'Produce 101 Japan' => 'PRODUCE 101 JAPAN THE GIRLS（日プ女子）',
        'なりきり（全也）' => 'なりきり',
        'クーポン・お得情報' => 'クーポン・無料配布',
        'ロック' => '邦ロック',
        '整形' => '美容整形',
        'ポケポケ（Pokémon TCG Pocket／ポケモンカード アプリ）' => 'ポケポケ（Pokémon TCG Pocket）',
        '就活生情報・選考対策・企業研究' => '企業研究',
    ];
}

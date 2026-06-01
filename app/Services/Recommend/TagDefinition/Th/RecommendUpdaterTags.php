<?php

declare(strict_types=1);

namespace App\Services\Recommend\TagDefinition\Th;

use App\Services\Recommend\TagDefinition\RecommendUpdaterTagsInterface;

class RecommendUpdaterTags implements RecommendUpdaterTagsInterface
{
    function getStrongestTags(?string $column = null): array
    {
        return [];
    }

    function getBeforeCategoryNameTags(): array
    {
        return [];
    }

    function getNameStrongTags(): array
    {
        return [
            // タイの強い検索需要「ดีล（=deal: お得情報・共同購入・通販セール）」を集約する
            // /th/recommend ハブ。本番DBで name/desc に「ดีล」を含む部屋は約 369 件あり、
            // 上位は WhatSale（Shopee/Lazada セール 7,823人・公式）, ดีลลับ โปรออนไลน์（5,065人）,
            // 共同購入/プレオーダー系など EC・お得情報コミュニティが大半でブランドセーフ。
            // 現状この需要は単一ルーム /th/oc/189451 だけに着地しており（/th/recommend に
            // 受け皿が無い）、ハブ化で 2 枠目の検索露出を作り単一ルーム依存も分散させる。
            //
            // matchesTagDef は配列形式 [tag, keywords] のとき keywords のみで照合するため、
            // 照合語にも「ดีล」を明示する。部分一致(mb_stripos)で ดีลลับ/กลุ่มดีล/บัตรดีล/
            // ดีลสินค้า 等の派生語も同時に拾える。formatTag はタイ語を無加工で返すため
            // ページ slug は「ดีล」になる。
            ['ดีล', ['ดีล']],
        ];
    }

    function getDescStrongTags(): array
    {
        return [];
    }

    function getAfterDescStrongTags(): array
    {
        return [];
    }

    function getSubCategoriesTag(): array
    {
        // th は LINE 公式 crawled subcategories.json (openChatSubCategories) を
        // RecommendUpdater 側で FileStorage 経由で読み続けるため、ここでは空配列を返す。
        return [];
    }
}

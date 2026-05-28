<?php

namespace App\Services\Recommend\TagDefinition;

interface RecommendUpdaterTagsInterface
{
    /**
     * @param 'oc.name'|'oc.description'|null $column
     * @return array<string,(string|array{string, string[]})[]>
     */
    function getStrongestTags(?string $column = null): array;

    /**
     * @return array<string,(string|array{string,string[]})[]>
     */
    function getBeforeCategoryNameTags(): array;

    /**
     * @return (string|array{string,string[]})[]
     */
    function getNameStrongTags(): array;

    /**
     * @return (string|array{string,string[]})[]
     */
    function getDescStrongTags(): array;

    /**
     * @return (string|array{string,string[]})[]
     */
    function getAfterDescStrongTags(): array;

    /**
     * LINEカテゴリID別のサブカテゴリタグ定義。
     *
     * 旧 storage/{locale}/open_chat_sub_categories/subcategories_tag.json を ja.json の
     * "subCategoriesTag" セクションへ移行した結果のもの。
     *
     * 戻り値は `{category_id: [tagDef, ...]}` の形（旧実装の getOpenChatSubCategoriesTag と同形）。
     * tagDef は keywords 有りなら `[tag, keywords[]]`、無しなら `tag(string)`。
     *
     * tw/th は本メソッドでは空配列を返し、locale 固有の crawled subcategories.json
     * (storage/{tw|th}/open_chat_sub_categories/subcategories.json) は RecommendUpdater 側で
     * 引き続き FileStorage 経由で読み込む。
     *
     * @return array<string,(string|array{string,string[]})[]>
     */
    function getSubCategoriesTag(): array;
}

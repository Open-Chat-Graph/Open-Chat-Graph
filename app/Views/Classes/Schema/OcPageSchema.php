<?php

namespace App\Views\Schema;

use Spatie\SchemaOrg\Schema;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\Enum\RecommendListType;
use Shared\MimimalCmsConfig;

class OcPageSchema
{
    function __construct(
        private PageBreadcrumbsListSchema $schema
    ) {}

    /**
     * @param array{0: RecommendListDto|false, 1: RecommendListDto|false, 2: string|false} $recommend
     */
    function generateSchema(
        string $title,
        string $description,
        \DateTimeInterface $datePublished,
        \DateTimeInterface $dateModified,
        array $recommend,
        array $oc
    ): string {
        $name = $oc['name'];
        $tags = array_filter(
            $recommend,
            fn($r) => $r instanceof RecommendListDto ? ($r->type === RecommendListType::Tag ? $r->listName : false) : false,
        );

        $recommendSection = array_map(fn(RecommendListDto $r) => "「{$r->listName}」のおすすめ", $tags);

        // WebPageの構築
        $webPage = Schema::article()
            ->publisher($this->schema->publisher())
            ->author($this->schema->person())
            ->headline($title)
            ->description($description)
            ->image(imgUrl($oc['id'], $oc['img_url']))
            ->datePublished($datePublished)
            ->dateModified(new \DateTime($oc['updated_at']))
            ->articleSection(
                [
                    $name,
                    t('メンバー数の推移グラフ'),
                    t('ランキングの順位表示'),
                    ...$recommendSection,
                ] + (
                    // TODO: 日本語以外ではコメントが無効
                    MimimalCmsConfig::$urlRoot === ''
                    ? [
                        'オープンチャットについてのコメント',
                        'コメントする',
                    ]
                    : []
                )
            )
            ->potentialAction($this->schema->potentialAction());

        if (MimimalCmsConfig::$urlRoot === '')
            $webPage->mainEntity($this->schema->room($oc));

        // JSON-LDのマークアップを生成
        return $webPage->toScript();
    }
}

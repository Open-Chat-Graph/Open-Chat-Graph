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
        $dataset = Schema::dataset()
            ->name("LINE Open Chat '{$name}' Member Count Trend")
            ->description(
                "The member count trend of the Open Chat '{$name}' is recorded daily. By accessing the URL, you can view it in a graph. The data is available for download in CSV format, containing the member count and dates for the entire period."
            )
            ->publisher(
                $this->schema->publisher()
            )
            ->creator(
                $this->schema->person()
            )
            ->keywords([
                "LINE Open Chat",
                "Member Count",
            ])
            ->provider(
                $this->schema->lineOcOrganization()
            )
            ->license('https://creativecommons.org/licenses/by/4.0/legalcode')
            ->url(url('oc/' . $oc['id']))
            ->datePublished($datePublished)
            ->dateModified($dateModified)
            ->image([
                imgUrl($oc['id'], $oc['img_url']),
            ])
            ->distribution(
                Schema::dataDownload()
                    ->encodingFormat('CSV')
                    ->contentUrl(url('oc/' . $oc['id'] . '/csv'))
            )
            ->variableMeasured('http://schema.org/FollowAction')
            ->measurementTechnique('Recording member count data from the official LINE Open Chat website.');

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
            ->mainEntity($this->schema->room($oc))
            ->mainEntityOfPage($dataset)
            ->potentialAction($this->schema->potentialAction());

        // JSON-LDのマークアップを生成
        return $webPage->toScript();
    }
}

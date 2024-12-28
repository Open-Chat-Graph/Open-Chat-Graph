<?php

namespace App\Views\Schema;

use Spatie\SchemaOrg\Schema;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\Enum\RecommendListType;

class OcPageSchema
{
    function __construct(
        private PageBreadcrumbsListSchema $schema
    ) {
    }

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
            ->name("LINEオープンチャット「{$name}」のメンバー数推移")
            ->description(
                "オープンチャット「{$name}」のメンバー数推移を日毎に記録しています。URLにアクセスするとグラフで表示されます。CSV形式でダウンロード可能です。CSVファイルは日付、メンバー数からなる全期間のデータになっています。"
            )
            ->publisher(
                $this->schema->publisher()
            )
            ->creator(
                $this->schema->person()
            )
            ->keywords([
                "LINEオープンチャット",
                "参加者数",
                "統計",
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
            ->measurementTechnique('LINEオープンチャットの公式サイトからメンバー数データを記録');

        $tags = array_filter(
            $recommend,
            fn ($r) => $r instanceof RecommendListDto ? ($r->type === RecommendListType::Tag ? $r->_listName : false) : false,
        );

        $recommendSection = array_map(fn (RecommendListDto $r) => "「{$r->_listName}」のおすすめ", $tags);

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
                    'メンバー数の推移グラフ',
                    'ランキングの順位表示',
                    ...$recommendSection,
                    'オープンチャットについてのコメント',
                    'コメントする',
                ]
            )
            ->mainEntity($this->schema->room($oc))
            ->mainEntityOfPage($dataset)
            ->potentialAction($this->schema->potentialAction());

        // JSON-LDのマークアップを生成
        return $webPage->toScript();
    }
}

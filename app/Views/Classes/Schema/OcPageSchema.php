<?php

namespace App\Views\Schema;

use App\Config\AppConfig;
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

        // 統計・分析ページとしてのWebPage構築
        $webPage = Schema::webPage()
            ->publisher($this->schema->publisher())
            ->author($this->schema->person())
            ->name($title)
            ->description($description)
            ->image(imgUrl($oc['id'], $oc['img_url']))
            ->datePublished($datePublished)
            ->dateModified($dateModified)
            ->specialty('Statistics')
            ->about(
                Schema::organization()
                    ->name($name)
                    ->description($oc['description'])
                    ->url(AppConfig::LINE_OPEN_URL[MimimalCmsConfig::$urlRoot] . $oc['emid'] . AppConfig::LINE_OPEN_URL_SUFFIX)
                    ->logo(imgUrl($oc['id'], $oc['img_url']))
                    ->aggregateRating(
                        Schema::aggregateRating()
                            ->ratingValue(5)
                            ->reviewCount($oc['member'])
                    )
            )
            ->isPartOf(
                Schema::webSite()
                    ->name(t('オプチャグラフ'))
                    ->url(url(['urlRoot' => '', 'paths' => []]))
                    ->description(t('LINEオープンチャットのメンバー数推移を追跡・分析するサービス'))
            )
            ->keywords([
                $name,
                t('メンバー数の推移グラフ'),
                t('ランキングの順位表示'),
                'LINE OpenChat',
                t('統計'),
                t('分析'),
                ...$recommendSection,
            ] + (
                // TODO: 日本語以外ではコメントが無効
                MimimalCmsConfig::$urlRoot === ''
                ? [
                    'オープンチャットについてのコメント',
                    'コメントする',
                ]
                : []
            ))
            ->potentialAction($this->schema->potentialAction());

        if (MimimalCmsConfig::$urlRoot === '') {
            $webPage->mainEntity(
                Schema::dataset()
                    ->name($name . 'の統計データ')
                    ->description($name . 'のLINEオープンチャットにおけるメンバー数の推移、1時間・1日・1週間の増減率、全体およびカテゴリ別ランキングでの順位変動などの詳細な統計情報を提供します。')
                    ->url(url('oc/' . $oc['id']))
                    ->dateModified($dateModified)
                    ->creator($this->schema->publisher())
                    ->about($this->schema->room($oc))
                    ->measurementTechnique('定期的なクローリングによるデータ収集')
                    ->temporalCoverage($datePublished->format('Y-m-d') . '/' . $dateModified->format('Y-m-d'))
                    ->license(Schema::creativeWork()
                        ->name('CC BY 4.0')
                        ->url('https://creativecommons.org/licenses/by/4.0/')
                    )
            );
        }

        // JSON-LDのマークアップを生成
        return $webPage->toScript();
    }
}

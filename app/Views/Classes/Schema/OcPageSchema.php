<?php

namespace App\Views\Schema;

use App\Config\AppConfig;
use Shared\MimimalCmsConfig;
use Spatie\SchemaOrg\Schema;

class OcPageSchema
{
    function __construct(
        private PageBreadcrumbsListSchema $schema
    ) {}

    function generateSchema(
        string $title,
        string $description,
        \DateTimeInterface $datePublished,
        \DateTimeInterface $dateModified,
        array $oc
    ): string {
        // シンプルなWebPageの構築
        $webPage = Schema::webPage()
            ->inLanguage($this->schema->getLocale())
            ->publisher($this->schema->publisher())
            ->name($title)
            ->description(preg_replace('/\s+/', ' ', str_replace(["\n", "\r"], ' ', $description)))
            ->image(imgUrl($oc['id'], $oc['img_url']))
            ->datePublished($datePublished)
            ->dateModified($dateModified);

        // aboutフィールドの追加 - OpenChatの情報
        $webPage->about(
            Schema::discussionForumPosting()
                ->headline($oc['name'])
                ->image(imgUrl($oc['id'], $oc['img_url']))
                ->url(AppConfig::LINE_OPEN_URL[MimimalCmsConfig::$urlRoot] . $oc['emid'] . AppConfig::LINE_OPEN_URL_SUFFIX)
                ->author(
                    Schema::organization()
                        ->name('LINE OpenChat')
                        ->url(str_replace('/cover/', '', AppConfig::LINE_OPEN_URL[MimimalCmsConfig::$urlRoot]))
                )
                ->datePublished($datePublished)
        );

        // mainEntityの追加 - データセット情報
        $webPage->mainEntity(
            Schema::dataset()
                ->name(sprintf(t('LINEオープンチャット「%s」統計データ'), $oc['name']))
                ->description(t('このデータセットには、LINEオープンチャットのメンバー数の時系列変化、日別・時間別の成長率、参加者数の推移に関する詳細な統計情報が含まれています。データは1時間ごとに自動収集され、トレンド分析や人気度の測定に活用されます。'))
                ->temporalCoverage($datePublished->format('Y-m-d') . '/' . (new \DateTime() >= new \DateTime('today 06:00') ? (new \DateTime('today 06:00'))->format('Y-m-d') : (new \DateTime('yesterday 06:00'))->format('Y-m-d')))
                ->creator(
                    Schema::organization()
                        ->name(t('オプチャグラフ'))
                        ->url(url())
                )
                ->license('https://creativecommons.org/licenses/by/4.0/')
        );

        // JSON-LDのマークアップを生成
        return $webPage->toScript();
    }
}

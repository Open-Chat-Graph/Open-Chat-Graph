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
                ->name($oc['name'])
                ->url(AppConfig::LINE_OPEN_URL[MimimalCmsConfig::$urlRoot] . $oc['emid'] . AppConfig::LINE_OPEN_URL_SUFFIX)
        );

        // mainEntityの追加 - データセット情報
        $webPage->mainEntity(
            Schema::dataset()
                ->name(sprintf(t('LINEオープンチャット「%s」統計データ'), $oc['name']))
                ->temporalCoverage($datePublished->format('Y-m-d') . '/' . (new \DateTime() >= new \DateTime('today 06:00') ? (new \DateTime('today 06:00'))->format('Y-m-d') : (new \DateTime('yesterday 06:00'))->format('Y-m-d')))
        );

        // JSON-LDのマークアップを生成
        return $webPage->toScript();
    }
}

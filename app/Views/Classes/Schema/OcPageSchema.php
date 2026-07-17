<?php

namespace App\Views\Schema;

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
        array $oc,
        ?array $metrics = null,
    ): string {
        // シンプルなWebPageの構築
        $webPage = Schema::webPage()
            ->inLanguage($this->schema->getLocale())
            ->publisher($this->schema->publisher())
            ->name($title)
            ->description(preg_replace('/\s+/', ' ', str_replace(["\n", "\r"], ' ', $description)))
            ->url(url('oc', (string)$oc['id']))
            ->image(imgUrl($oc['img_url']))
            ->datePublished($datePublished)
            ->dateModified($dateModified);

        // about は当サイトが観測する対象そのもの。投稿本文を掲載するページではないため、
        // DiscussionForumPosting は使用しない。
        // 部屋名は DB 生値で `<`/`>` を含みうる。spatie の toScript() は json_encode(JSON_UNESCAPED_SLASHES)
        // で `<`/`/` を素通しするため、"</script>" を含む名前で JSON-LD が途中終端し格納型XSSになる。
        // jsonLdText() で `<`/`>` を無効化してから埋め込む（$title は既に h() 済みなので対象外）。
        $webPage->about(
            Schema::thing()
                ->name(jsonLdText($oc['name']))
                ->image(imgUrl($oc['img_url']))
                ->url(url('oc', (string)$oc['id']))
        );

        // mainEntityの追加 - データセット情報
        $dataset = Schema::dataset()
                ->name(sprintf(t('LINEオープンチャット「%s」統計データ'), jsonLdText($oc['name'])))
                ->description(t('このデータセットには、LINEオープンチャットのメンバー数の時系列変化、日別・時間別の成長率、参加者数の推移に関する詳細な統計情報が含まれています。データは1時間ごとに自動収集され、トレンド分析や人気度の測定に活用されます。'))
                ->creator(
                    Schema::organization()
                        ->name(t('オプチャグラフ'))
                        ->url(url())
                );

        if (!empty($metrics['observed_from']) && !empty($metrics['observed_at'])) {
            $dataset->temporalCoverage($metrics['observed_from'] . '/' . $metrics['observed_at']);
        }
        $dataset->variableMeasured([
            t('メンバー数'),
            t('1時間・24時間・7日・30日のメンバー数変化'),
            t('観測期間中のピーク人数'),
        ]);
        $webPage->mainEntity($dataset);

        // JSON-LDのマークアップを生成
        return $webPage->toScript();
    }
}

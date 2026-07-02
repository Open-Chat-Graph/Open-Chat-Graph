<?php

namespace App\Views\Schema;

use App\Config\AppConfig;
use App\Views\Meta\Metadata;
use Spatie\SchemaOrg\Schema;

class PageBreadcrumbsListSchema
{
    public string $publisherName;
    public string $publisherLogo;

    function __construct(
        private Metadata $metadata
    ) {
        $this->publisherName = t('オプチャグラフ');
        $this->publisherLogo = url(['urlRoot' => '', 'paths' => ['assets/icon-192x192.png']]);
    }

    // パンくずリスト
    function generateSchema(string $listItemName, string $path = '', string $secondName = '', string $secondPath = '', bool $fullPath = false): string
    {
        $breadcrumbList = Schema::breadcrumbList();

        $breadcrumbList->inLanguage($this->metadata->locale);

        if ($path) {
            $itemListElement = [
                Schema::listItem()
                    ->position(1)
                    ->name(t('トップ'))
                    ->item(rtrim(url(), '/')),
                Schema::listItem()
                    ->position(2)
                    ->name($listItemName)
                    ->item(url($path)),
            ];
        } else {
            $itemListElement = [
                Schema::listItem()
                    ->position(1)
                    ->name(t('トップ'))
                    ->item(rtrim(url(), '/')),
                Schema::listItem()
                    ->position(2)
                    ->name($listItemName),
            ];
        }

        if ($secondName && $secondPath) {
            $itemListElement[] = Schema::listItem()
                ->position(3)
                ->name($secondName)
                ->item(url($fullPath ? $secondPath : ($path . '/' . $secondPath)));
        } elseif ($secondName) {
            $itemListElement[] = Schema::listItem()
                ->position(3)
                ->name($secondName);
        }

        $breadcrumbList->itemListElement($itemListElement);

        return $breadcrumbList->toScript();
    }

    // organization
    function publisher()
    {
        return Schema::organization()
            ->name($this->publisherName)
            ->url(url())
            ->logo($this->publisherLogo)
            ->sameAs(AppConfig::BRAND_SAME_AS);
    }



    /**
     * @param array<int,array<string,mixed>> $rooms ランキング表示中の部屋（表示順・id/name キーを使用）。
     *   渡すと mainEntity: ItemList として埋め込み、検索エンジン・AI検索(ChatGPT等)が
     *   「このテーマの上位の部屋」を機械可読に引用できるようにする
     */
    function generateRecommend(
        string $title,
        string $description,
        \DateTimeInterface $dateModified,
        string $tag,
        array $rooms = []
    ): string {
        $collectionPage = Schema::collectionPage()
            ->inLanguage($this->metadata->locale)
            ->name($title)
            ->description($description)
            ->publisher($this->publisher())
            ->dateModified($dateModified)
            ->about(Schema::thing()->name($tag));

        if ($rooms) {
            $items = [];
            foreach (array_values($rooms) as $i => $room) {
                $items[] = Schema::listItem()
                    ->position($i + 1)
                    ->url(url('oc', (string)$room['id']))
                    // 部屋名は DB 生値。spatie の toScript() は `<`/`/` を素通しするため
                    // jsonLdText() で `<`/`>` を無効化してから埋め込む（"</script>" 破断=XSS防止）
                    ->name(jsonLdText((string)$room['name']));
            }
            $collectionPage->mainEntity(
                Schema::itemList()
                    ->numberOfItems(count($items))
                    ->itemListOrder('https://schema.org/ItemListOrderDescending')
                    ->itemListElement($items)
            );
        }

        return $collectionPage->toScript();
    }


    function getLocale(): string
    {
        return $this->metadata->locale;
    }
}

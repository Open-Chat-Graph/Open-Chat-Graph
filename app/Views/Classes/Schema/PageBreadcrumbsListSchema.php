<?php

namespace App\Views\Schema;

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
            ->logo($this->publisherLogo);
    }



    function generateRecommend(
        string $title,
        string $description,
        string $url,
        \DateTimeInterface $datePublished,
        \DateTimeInterface $dateModified,
        string $tag,
        array $rooms // オープンチャットルームの情報を配列で追加
    ): string {
        // 各オープンチャットルームをItemListとして追加
        $itemList = Schema::itemList();

        $listArray = [];
        foreach ($rooms as $index => $room) {
            $listArray[] = Schema::listItem()
                ->item(
                    schema::article()
                        ->headline($room['name'])
                        ->description($room['description'])
                        ->image(imgPreviewUrl($room['id'], $room['img_url']))
                        ->url(url('oc/' . $room['id']))
                        ->position($index + 1)
                );
        }

        $itemList->itemListElement($listArray);

        $webSite = Schema::article()
            ->headline($title)
            ->description($description)
            ->image(imgUrl($rooms[0]['id'], $rooms[0]['img_url']))
            ->publisher($this->publisher())
            ->datePublished($datePublished)
            ->dateModified($dateModified)
            ->articleSection([$title, t('関連のテーマ')])
            ->about(Schema::thing()->name($tag))
            ->mainEntityOfPage(
                Schema::collectionPage()
                    ->id($url)
            )
            ->mainEntity($itemList);

        return $webSite->toScript();
    }


    function getLocale(): string
    {
        return $this->metadata->locale;
    }
}

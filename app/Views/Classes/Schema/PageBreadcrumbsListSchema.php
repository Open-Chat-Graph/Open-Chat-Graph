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
        \DateTimeInterface $dateModified,
        string $tag
    ): string {
        $collectionPage = Schema::collectionPage()
            ->inLanguage($this->metadata->locale)
            ->name($title)
            ->description($description)
            ->publisher($this->publisher())
            ->dateModified($dateModified)
            ->about(Schema::thing()->name($tag));

        return $collectionPage->toScript();
    }


    function getLocale(): string
    {
        return $this->metadata->locale;
    }
}

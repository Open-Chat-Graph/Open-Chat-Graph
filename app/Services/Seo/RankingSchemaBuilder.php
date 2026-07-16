<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Services\PublicApi\Dto\RoomResource;

final class RankingSchemaBuilder
{
    /** @param array<int,array{position:int,room:RoomResource,change:?int}> $items */
    public function build(string $canonical, string $title, string $description, string $updatedAt, array $items): string
    {
        $elements = [];
        foreach ($items as $item) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $item['position'],
                'url' => $item['room']->canonicalUrl,
                'name' => $item['room']->name,
            ];
        }
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'url' => $canonical,
            'name' => $title,
            'description' => $description,
            'dateModified' => (new \DateTimeImmutable($updatedAt))->format(\DateTimeInterface::RFC3339),
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => count($elements),
                'itemListOrder' => 'https://schema.org/ItemListOrderDescending',
                'itemListElement' => $elements,
            ],
        ];
        return '<script type="application/ld+json">'
            . json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)
            . '</script>';
    }
}

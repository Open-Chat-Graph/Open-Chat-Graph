<?php

declare(strict_types=1);

namespace App\Services\Recommend\TagDefinition\Ja;

use App\Services\Recommend\TagDefinition\TagMetadata;
use Shared\MimimalCmsConfig;

class RecommendTagFilters
{
    /** @return string[] */
    static function recommendPageTagFilter(): array
    {
        return TagMetadata::recommendPageTagFilter();
    }

    /** @return array<string,string[]> */
    static function filteredTagSort(): array
    {
        return TagMetadata::filteredTagSort();
    }

    /** @return array<string,string> */
    static function redirectTags(): array
    {
        return TagMetadata::redirects();
    }

    static function getTopPageTagFilter(): array
    {
        if (MimimalCmsConfig::$urlRoot !== '') {
            return [];
        }

        return array_merge(TagMetadata::recommendPageTagFilter(), TagMetadata::topPageTagFilter());
    }
}

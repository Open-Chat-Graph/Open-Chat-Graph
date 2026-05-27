<?php

declare(strict_types=1);

namespace App\Services\Recommend\TagDefinition\Ja;

use App\Services\Recommend\TagDefinition\JaTagMetadata;
use Shared\MimimalCmsConfig;

class RecommendTagFilters
{
    /** @return string[] */
    static function recommendPageTagFilter(): array
    {
        return JaTagMetadata::recommendPageTagFilter();
    }

    /** @return array<string,string[]> */
    static function filteredTagSort(): array
    {
        return JaTagMetadata::filteredTagSort();
    }

    /** @return array<string,string> */
    static function redirectTags(): array
    {
        return JaTagMetadata::redirects();
    }

    static function getTopPageTagFilter(): array
    {
        if (MimimalCmsConfig::$urlRoot !== '') {
            return [];
        }

        return array_merge(JaTagMetadata::recommendPageTagFilter(), JaTagMetadata::topPageTagFilter());
    }
}

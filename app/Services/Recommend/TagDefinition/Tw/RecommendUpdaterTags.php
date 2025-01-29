<?php

declare(strict_types=1);

namespace App\Services\Recommend\TagDefinition\Tw;

use App\Services\Recommend\TagDefinition\RecommendUpdaterTagsInterface;

class RecommendUpdaterTags implements RecommendUpdaterTagsInterface
{
    function getBeforeCategoryNameTags(): array
    {
        return [];
    }

    function getNameStrongTags(): array
    {
        return [];
    }

    function getDescStrongTags(): array
    {
        return [];
    }

    function getAfterDescStrongTags(): array
    {
        return [];
    }
}

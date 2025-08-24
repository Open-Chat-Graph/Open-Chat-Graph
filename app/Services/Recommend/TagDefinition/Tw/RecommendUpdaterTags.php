<?php

declare(strict_types=1);

namespace App\Services\Recommend\TagDefinition\Tw;

use App\Services\Recommend\TagDefinition\RecommendUpdaterTagsInterface;

class RecommendUpdaterTags implements RecommendUpdaterTagsInterface
{
    function getStrongestTags(?string $column = null): array
    {
        return [];
    }

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

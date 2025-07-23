<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\StaticData\RecommendStaticDataFile;

class RecommendPageList
{
    function __construct(
        private RecommendStaticDataFile $recommendStaticDataGenerator,
        private RecommendUpdater $recommendUpdater,
    ) {
    }

    function getListDto(string $tag): RecommendListDto|false
    {
        return $this->recommendStaticDataGenerator->getRecomendRanking($tag);
    }

    function getValidTag(string $tag): string|false
    {
        $tags = $this->recommendUpdater->getAllTagNames();
        $lowercaseTag = strtolower($tag);
        foreach ($tags as $originalTag) {
            if (strtolower($originalTag) === $lowercaseTag) {
            return $originalTag;
            }
        }
        return false;
    }
}

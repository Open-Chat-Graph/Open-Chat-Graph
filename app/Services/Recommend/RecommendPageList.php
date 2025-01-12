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

    function isValidTag(string $tag): bool
    {
        $tags = $this->recommendUpdater->getAllTagNames();
        return in_array($tag, $tags);
    }
}

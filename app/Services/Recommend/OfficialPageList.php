<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\StaticData\RecommendStaticDataFile;

class OfficialPageList
{
    function __construct(
        private RecommendStaticDataFile $recommendStaticDataGenerator,
    ) {
    }

    function getListDto(int $emblem): RecommendListDto|false
    {
        return $this->recommendStaticDataGenerator->getOfficialRanking($emblem);
    }
}

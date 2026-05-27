<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\StaticData\RecommendStaticDataGenerator;

class OfficialPageList
{
    function __construct(
        private RecommendStaticDataGenerator $recommendStaticDataGenerator,
    ) {
    }

    function getListDto(int $emblem): RecommendListDto|false
    {
        return $this->recommendStaticDataGenerator->getOfficialRanking($emblem);
    }
}

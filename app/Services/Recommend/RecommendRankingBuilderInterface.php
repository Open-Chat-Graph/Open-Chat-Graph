<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Models\RecommendRepositories\AbstractRecommendRankingRepository;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\Enum\RecommendListType;

interface RecommendRankingBuilderInterface
{
    function getRanking(
        RecommendListType $type,
        string $entity,
        string $listName,
        AbstractRecommendRankingRepository $repository
    ): RecommendListDto;
}

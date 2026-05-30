<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\StaticData\RecommendStaticDataGenerator;

class RecommendGenarator
{
    function __construct(
        private RecommendStaticDataGenerator $recommendStaticDataGenerator
    ) {
    }

    /** @return array{0:RecommendListDto|false,1:RecommendListDto|false,2:string,3:RecommendListDto|false} */
    function getRecommend(?string $tag, ?string $tag2, ?string $tag3, ?int $category): array
    {
        if (!$tag) {
            return [
                false,
                false,
                '',
                $category ? $this->recommendStaticDataGenerator->getCategoryRanking($category) : false
            ];
        }

        if ($tag === $tag2) $tag2 = $tag3;
        return [
            $this->recommendStaticDataGenerator->getRecomendRanking($tag),
            $tag2 ? $this->recommendStaticDataGenerator->getRecomendRanking($tag2) : false,
            $tag,
            $category ? $this->recommendStaticDataGenerator->getCategoryRanking($category) : false
        ];
    }
}

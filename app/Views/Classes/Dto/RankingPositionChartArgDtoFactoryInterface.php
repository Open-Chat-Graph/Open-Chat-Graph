<?php

declare(strict_types=1);

namespace App\Views\Classes\Dto;

use App\Views\Dto\RankingPositionChartArgDto;

interface RankingPositionChartArgDtoFactoryInterface
{
    /**
     * @param array{id: int, category?: int|null, api_created_at?: int|string} $oc
     * @param string $categoryName
     * @return RankingPositionChartArgDto
     */
    public function create(array $oc, string $categoryName): RankingPositionChartArgDto;
}
<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Api;

use App\Config\SecretsConfig;
use App\Views\Classes\Dto\RankingPositionChartArgDtoFactoryInterface;
use App\Views\Dto\RankingPositionChartArgDto;
use Shared\MimimalCmsConfig;

class ApiRankingPositionChartArgDtoFactory implements RankingPositionChartArgDtoFactoryInterface
{
    /**
     * @param array{id: int, category?: int|null, api_created_at?: int|string} $oc
     * @param string $categoryName
     * @return RankingPositionChartArgDto
     */
    public function create(array $oc, string $categoryName): RankingPositionChartArgDto
    {
        $dto = new RankingPositionChartArgDto;
        $dto->id = $oc['id'];
        $dto->categoryKey = $oc['category'] ?? (is_int($oc['api_created_at']) ? 0 : null);
        $dto->categoryName = $categoryName;
        $dto->baseUrl = url('ranking-position', SecretsConfig::$adminApiKey);
        $dto->urlRoot = MimimalCmsConfig::$urlRoot;

        return $dto;
    }
}

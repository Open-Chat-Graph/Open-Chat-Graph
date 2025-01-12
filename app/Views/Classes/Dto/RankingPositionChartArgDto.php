<?php

declare(strict_types=1);

namespace App\Views\Dto;

class RankingPositionChartArgDto
{
    public int $id;
    public int|null $categoryKey;
    public string $categoryName;
    public string $baseUrl;
    public string $urlRoot;
}

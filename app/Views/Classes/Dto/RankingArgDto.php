<?php

declare(strict_types=1);

namespace App\Views\Dto;

class RankingArgDto
{
    public string $baseUrl;
    public string $rankingUpdatedAt;
    public string $modifiedUpdatedAtDate;
    public string $hourlyUpdatedAt;
    public array $subCategories;
    public string $urlRoot;
    /**
     * @var array{0:string,1:int}[]
     */
    public array $openChatCategory;
}

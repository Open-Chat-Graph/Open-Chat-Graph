<?php

declare(strict_types=1);

namespace App\ServiceProvider;

use App\Models\Repositories\Api\ApiRankingPositionPageRepository;
use App\Models\Repositories\RankingPosition\RankingPositionPageRepositoryInterface;

class ApiRankingPositionPageRepositoryServiceProvider implements ServiceProviderInterface
{
    function register(): void
    {
        app()->bind(RankingPositionPageRepositoryInterface::class, ApiRankingPositionPageRepository::class);
    }
}

<?php

declare(strict_types=1);

namespace App\ServiceProvider;

use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Models\Repositories\Api\ApiOpenChatPageRepository;
use App\Models\Repositories\Statistics\StatisticsPageRepositoryInterface;
use App\Models\Repositories\Api\ApiStatisticsPageRepository;

class ApiRepositoryServiceProvider implements ServiceProviderInterface
{
    function register(): void
    {
        app()->bind(OpenChatPageRepositoryInterface::class, ApiOpenChatPageRepository::class);

        app()->bind(StatisticsPageRepositoryInterface::class, ApiStatisticsPageRepository::class);
    }
}

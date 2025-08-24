<?php

declare(strict_types=1);

namespace App\ServiceProvider;

use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Models\Repositories\Api\ApiOpenChatPageRepository;
use App\Models\Repositories\Statistics\StatisticsPageRepositoryInterface;
use App\Models\Repositories\Api\ApiStatisticsPageRepository;
use App\Services\OpenChat\Api\ApiRankingPositionChartArgDtoFactory;
use App\Views\Classes\CollapseKeywordEnumerationsInterface;
use App\Views\Classes\Dto\RankingPositionChartArgDtoFactoryInterface;

class ApiDbOpenChatControllerServiceProvider implements ServiceProviderInterface
{
    function register(): void
    {
        app()->bind(OpenChatPageRepositoryInterface::class, ApiOpenChatPageRepository::class);
        app()->bind(StatisticsPageRepositoryInterface::class, ApiStatisticsPageRepository::class);
        app()->bind(RankingPositionChartArgDtoFactoryInterface::class, ApiRankingPositionChartArgDtoFactory::class);

        app()->bind(CollapseKeywordEnumerationsInterface::class, fn() => new class() implements CollapseKeywordEnumerationsInterface {
            static function collapse(
                string $text,
                int $minItems = 12,
                int $keepFirst = 1,
                int $allowHashtags = 1,
                string $extraText = '',
                bool $returnRemovedOnly = false,
                int $embeddedMinItems = 3
            ): string {
                return $text;
            }
        });
    }
}

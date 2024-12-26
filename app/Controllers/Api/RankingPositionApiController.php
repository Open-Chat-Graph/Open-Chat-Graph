<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\AppConfig;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Services\OpenChat\Enum\RankingType;
use App\Services\RankingPosition\Dto\RankingPositionChartDto;
use App\Services\RankingPosition\RankingPositionChartArrayService;
use App\Services\RankingPosition\RankingPositionHourChartArrayService;

class RankingPositionApiController
{
    function __construct(
        private OpenChatPageRepositoryInterface $openChatPageRepository
    ) {}

    function rankingPosition(
        RankingPositionChartArrayService $chart,
        int $open_chat_id,
        int $category,
        string $sort,
        string $start_date,
        string $end_date
    ) {
        if (strtotime($start_date) > strtotime(file_get_contents(AppConfig::$DAILY_CRON_UPDATED_AT_DATE))) {
            return response(
                get_object_vars(new RankingPositionChartDto) + [
                    'error' => 'Last Cron execution date is before start_date'
                ]
            );
        }

        return response($chart->getRankingPositionChartArray(
            RankingType::from($sort),
            $open_chat_id,
            $category,
            new \DateTime($start_date),
            new \DateTime($end_date)
        ));
    }

    function rankingPositionHour(
        RankingPositionHourChartArrayService $chart,
        int $open_chat_id,
        int $category,
        string $sort
    ) {
        return response($chart->getPositionHourChartArray(
            RankingType::from($sort),
            $open_chat_id,
            $category
        ));
    }
}

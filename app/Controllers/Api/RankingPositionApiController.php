<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\AppConfig;
use App\Services\OpenChat\Enum\RankingType;
use App\Services\RankingPosition\Dto\RankingPositionChartDto;
use App\Services\RankingPosition\RankingPositionChartArrayService;
use App\Services\RankingPosition\RankingPositionHourChartArrayService;
use App\Services\Storage\FileStorageInterface;

class RankingPositionApiController
{
    function rankingPosition(
        RankingPositionChartArrayService $chart,
        FileStorageInterface $fileStorage,
        int $open_chat_id,
        int $category,
        string $sort,
        string $start_date,
        string $end_date
    ) {
        if (strtotime($start_date) > strtotime($fileStorage->getContents('@dailyCronUpdatedAtDate'))) {
            return etag(response(
                get_object_vars(new RankingPositionChartDto) + [
                    'error' => 'Last Cron execution date is before start_date'
                ]
            ));
        }

        return etag(response($chart->getRankingPositionChartArray(
            RankingType::from($sort),
            $open_chat_id,
            $category,
            new \DateTime($start_date),
            new \DateTime($end_date)
        )));
    }

    function rankingPositionHour(
        RankingPositionHourChartArrayService $chart,
        int $open_chat_id,
        int $category,
        string $sort
    ) {
        return etag(response($chart->getPositionHourChartArray(
            RankingType::from($sort),
            $open_chat_id,
            $category
        )));
    }
}

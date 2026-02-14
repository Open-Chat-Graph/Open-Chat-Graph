<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\OpenChat\Enum\RankingType;
use App\Services\RankingPosition\Dto\RankingPositionChartDto;
use App\Services\RankingPosition\RankingPositionChartArrayService;
use App\Services\RankingPosition\RankingPositionHourChartArrayService;
use App\Models\Repositories\RankingPosition\RankingPositionOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsOhlcRepositoryInterface;
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

    /**
     * メンバー数OHLCデータを返す。
     *
     * - 元データは毎時ランキングクロールで取得したメンバー数（member テーブル）を日次集約したもの
     * - OHLC統計の記録開始以降のデータのみ（それ以前の日はレコードなし）
     * - フロントエンドはレコードがない日を日次メンバー数から擬似OHLCで補完する
     *
     * @return array{ date: string, open_member: int, high_member: int, low_member: int, close_member: int }[]
     */
    function memberOhlc(
        StatisticsOhlcRepositoryInterface $repo,
        int $open_chat_id
    ) {
        return response($repo->getOhlcDateAsc($open_chat_id));
    }

    /**
     * ランキング順位OHLCデータを返す。
     *
     * - 元データは毎時ランキングクロールの順位データ（ranking/rising テーブル）を日次集約したもの
     * - 特定のcategory+sort（type）でランキングに掲載されなかった日のレコードは含まれない
     * - フロントエンドはレコードがない日を圏外（position=0）として扱う
     * - low_position: 全時間帯でランクインしていた場合は最低順位、
     *   一部の時間帯で圏外だった場合は null
     *
     * @return array{ date: string, open_position: int, high_position: int, low_position: int|null, close_position: int }[]
     */
    function rankingPositionOhlc(
        RankingPositionOhlcRepositoryInterface $repo,
        int $open_chat_id,
        int $category,
        string $sort
    ) {
        return response($repo->getOhlcDateAsc($open_chat_id, $category, RankingType::from($sort)));
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

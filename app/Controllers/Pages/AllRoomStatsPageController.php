<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Models\Repositories\AllRoomStatsRepository;
use App\Models\Repositories\DB;
use App\Views\Schema\PageBreadcrumbsListSchema;

class AllRoomStatsPageController
{
    const Title = 'オープンチャット全体統計';
    const Desc = 'オプチャグラフに登録されている全オープンチャットの統計データ。総ルーム数・総参加者数・カテゴリー別のルーム数などを一覧表示します。';

    function index(
        AllRoomStatsRepository $repository,
        PageBreadcrumbsListSchema $breadcrumbsSchema,
    ) {
        DB::connect();

        $totalRooms = $repository->getTotalRoomCount();
        $totalMembers = $repository->getTotalMemberCount();
        $trackingStartDate = $repository->getTrackingStartDate();

        $deletedRoomsTotal = $repository->getDeletedRoomCount();
        $deletedRoomsHourly = $repository->getDeletedRoomCountSince('1 HOUR');
        $deletedRoomsDaily = $repository->getDeletedRoomCountSince('24 HOUR');
        $deletedRoomsWeekly = $repository->getDeletedRoomCountSince('7 DAY');

        $categoryStats = $repository->getCategoryStats();
        $hourlyIncrease = $repository->getHourlyMemberIncrease();
        $dailyIncrease = $repository->getDailyMemberIncrease();
        $weeklyIncrease = $repository->getWeeklyMemberIncrease();

        $_css = ['site_header', 'site_footer', 'terms'];
        $_meta = meta()->setTitle(self::Title);
        $_meta->setDescription(self::Desc)->setOgpDescription(self::Desc);
        $_breadcrumbsSchema = $breadcrumbsSchema->generateSchema('Labs', 'labs', self::Title);

        return view('all_room_stats_content', compact(
            '_meta',
            '_css',
            '_breadcrumbsSchema',
            'totalRooms',
            'totalMembers',
            'trackingStartDate',
            'deletedRoomsTotal',
            'deletedRoomsHourly',
            'deletedRoomsDaily',
            'deletedRoomsWeekly',
            'categoryStats',
            'hourlyIncrease',
            'dailyIncrease',
            'weeklyIncrease',
        ));
    }
}

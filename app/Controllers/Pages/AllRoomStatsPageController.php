<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Models\Repositories\AllRoomStatsRepositoryInterface;
use App\Models\Repositories\DB;
use App\Services\Storage\FileStorageInterface;
use App\Views\Schema\PageBreadcrumbsListSchema;

class AllRoomStatsPageController
{
    const Title = 'オープンチャット全体統計';
    const Desc = 'オプチャグラフに登録されている全オープンチャットの統計データ。総ルーム数・総参加者数・カテゴリー別のルーム数などを一覧表示します。';

    function index(
        AllRoomStatsRepositoryInterface $repository,
        PageBreadcrumbsListSchema $breadcrumbsSchema,
        FileStorageInterface $fileStorage,
    ) {
        DB::connect();

        $updatedAt = $fileStorage->getContents('@hourlyCronUpdatedAtDatetime');

        $totalRooms = $repository->getTotalRoomCount();
        $totalMembers = $repository->getTotalMemberCount();
        $trackingStartDate = $repository->getTrackingStartDate();

        $newRoomsMonthly = $repository->getNewRoomCountSince('1 MONTH');
        $newRoomsWeekly = $repository->getNewRoomCountSince('7 DAY');
        $newRoomsDaily = $repository->getNewRoomCountSince('24 HOUR');
        $newRoomsHourly = $repository->getNewRoomCountSince('1 HOUR');

        $earliestDeletedDate = $repository->getEarliestDeletedDate();
        $deletedRoomsTotal = $repository->getDeletedRoomCount();
        $deletedRoomsMonthly = $repository->getDeletedRoomCountSince('1 MONTH');
        $deletedRoomsWeekly = $repository->getDeletedRoomCountSince('7 DAY');
        $deletedRoomsDaily = $repository->getDeletedRoomCountSince('24 HOUR');
        $deletedRoomsHourly = $repository->getDeletedRoomCountSince('1 HOUR');

        $deletedMembersTotal = $repository->getDeletedMemberCountTotal();
        $deletedMembersMonthly = $repository->getDeletedMemberCountSince('1 MONTH');
        $deletedMembersWeekly = $repository->getDeletedMemberCountSince('7 DAY');
        $deletedMembersDaily = $repository->getDeletedMemberCountSince('24 HOUR');
        $deletedMembersHourly = $repository->getDeletedMemberCountSince('1 HOUR');

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
            'updatedAt',
            'totalRooms',
            'totalMembers',
            'trackingStartDate',
            'newRoomsMonthly',
            'newRoomsWeekly',
            'newRoomsDaily',
            'newRoomsHourly',
            'earliestDeletedDate',
            'deletedRoomsTotal',
            'deletedRoomsMonthly',
            'deletedRoomsWeekly',
            'deletedRoomsDaily',
            'deletedRoomsHourly',
            'deletedMembersTotal',
            'deletedMembersMonthly',
            'deletedMembersWeekly',
            'deletedMembersDaily',
            'deletedMembersHourly',
            'categoryStats',
            'hourlyIncrease',
            'dailyIncrease',
            'weeklyIncrease',
        ));
    }
}

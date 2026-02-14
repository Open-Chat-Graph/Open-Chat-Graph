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

        $newRoomsMonthly = $repository->getNewRoomCountSince('1 month');
        $newRoomsWeekly = $repository->getNewRoomCountSince('7 day');
        $newRoomsDaily = $repository->getNewRoomCountSince('24 hour');
        $newRoomsHourly = $repository->getNewRoomCountSince('1 hour');

        $deletedRoomsMonthly = $repository->getDeletedRoomCountSince('1 month');
        $deletedRoomsWeekly = $repository->getDeletedRoomCountSince('7 day');
        $deletedRoomsDaily = $repository->getDeletedRoomCountSince('24 hour');
        $deletedRoomsHourly = $repository->getDeletedRoomCountSince('1 hour');

        $deletedMembersMonthly = $repository->getDeletedMemberCountSince('1 month');
        $deletedMembersWeekly = $repository->getDeletedMemberCountSince('7 day');
        $deletedMembersDaily = $repository->getDeletedMemberCountSince('24 hour');
        $deletedMembersHourly = $repository->getDeletedMemberCountSince('1 hour');

        $categoryStats = $repository->getCategoryStats();

        // メンバー増減（sqlapi.db daily_member_statistics、日単位）
        $dailyTrend = $repository->getMemberTrend('-1 day');
        $weeklyTrend = $repository->getMemberTrend('-7 day');
        $monthlyTrend = $repository->getMemberTrend('-1 month');

        // 掲載終了ルーム（sqlapi.db、日単位）
        $delistedDaily = $repository->getDelistedStats('-1 day');
        $delistedWeekly = $repository->getDelistedStats('-7 day');
        $delistedMonthly = $repository->getDelistedStats('-1 month');

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
            'deletedRoomsMonthly',
            'deletedRoomsWeekly',
            'deletedRoomsDaily',
            'deletedRoomsHourly',
            'deletedMembersMonthly',
            'deletedMembersWeekly',
            'deletedMembersDaily',
            'deletedMembersHourly',
            'categoryStats',
            'dailyTrend',
            'weeklyTrend',
            'monthlyTrend',
            'delistedDaily',
            'delistedWeekly',
            'delistedMonthly',
        ));
    }
}

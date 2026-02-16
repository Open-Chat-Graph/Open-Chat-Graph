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
        $overallMedian = $repository->getOverallMedian();

        $newRoomsMonthly = $repository->getNewRoomCountSince('1 month');

        $deletedRoomsMonthly = $repository->getDeletedRoomCountSince('1 month');
        $deletedMembersMonthly = $repository->getDeletedMemberCountSince('1 month');

        $categoryStats = $repository->getCategoryStatsWithMedianAndTrend();

        $monthlyTrend = $repository->getMemberTrend('-1 month');

        $delistedMonthly = $repository->getDelistedStats('-1 month');

        $memberDistribution = $repository->getMemberDistribution();

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
            'overallMedian',
            'newRoomsMonthly',
            'deletedRoomsMonthly',
            'deletedMembersMonthly',
            'categoryStats',
            'monthlyTrend',
            'delistedMonthly',
            'memberDistribution',
        ));
    }
}

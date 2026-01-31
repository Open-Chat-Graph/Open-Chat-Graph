<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Models\CommentRepositories\RecentCommentListRepositoryInterface;
use App\Services\Recommend\OfficialPageList;
use App\Services\StaticData\StaticDataFile;
use App\Views\Schema\PageBreadcrumbsListSchema;
use DateTime;

class IndexPageController
{
    function index(
        StaticDataFile $staticDataGeneration,
        RecentCommentListRepositoryInterface $recentCommentListRepository,
        PageBreadcrumbsListSchema $pageBreadcrumbsListSchema,
        OfficialPageList $officialPageList,
    ) {
        AppConfig::$listLimitTopRanking = 10;
        $dto = $staticDataGeneration->getTopPageData();

        $_css = ['room_list', 'site_header', 'site_footer', 'search_form', 'recommend_list', 'recommend_page'];
        $_meta = meta();
        $_meta->title = "{$_meta->title}｜" . t('オープンチャットの統計情報');

        $_schema = $_meta->generateTopPageSchema(
            t('オプチャグラフ'),
            $_meta->description,
            url(),
            url('assets/ogp.png'),
            new DateTime('2023-05-06 00:00:00'),
            $dto->hourlyUpdatedAt
        );

        $dto->recentCommentList = $recentCommentListRepository->findRecentCommentOpenChatAll(
            0,
            15,
        );

        $updatedAtHouryCron = $dto->rankingUpdatedAt;

        if (isset($dto->recentCommentList[0]['time'])) {
            $updatedAtComments = new \DateTime($dto->recentCommentList[0]['time']);
            $_updatedAt = $updatedAtHouryCron > $updatedAtComments ? $updatedAtHouryCron : $updatedAtComments;
        } else {
            $_updatedAt = $updatedAtHouryCron;
        }

        $officialDto = $officialPageList->getListDto(1);
        $officialDto2 = $officialPageList->getListDto(2);

        return view('top_content', compact(
            '_meta',
            '_css',
            '_schema',
            '_updatedAt',
            'dto',
            'officialDto',
            'officialDto2',
        ));
    }
}

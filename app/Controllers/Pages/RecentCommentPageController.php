<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Services\StaticData\StaticDataFile;
use App\Views\RecentComment;
use App\Views\RecentCommentSelectElementPagination;
use App\Views\Schema\PageBreadcrumbsListSchema;
use Shadow\Kernel\Reception;

class RecentCommentPageController
{
    function __construct(
        private RecentComment $recentComment,
        private RecentCommentSelectElementPagination $pagination,
        private PageBreadcrumbsListSchema $breadcrumbsShema,

    ) {}

    function index(
        StaticDataFile $staticDataGeneration,
    ) {
        $unmodifeidPageNumber = Reception::input('page');
        $rankingList = $this->recentComment->getAllOrderByRegistrationDate(
            $unmodifeidPageNumber,
            AppConfig::LIST_LIMIT_RECENT_COMMENT
        );

        if (!$rankingList) {
            // 最大ページ数を超えてる場合は404
            return false;
        }

        $path = 'comments-timeline';
        $pageTitle = 'コメントのタイムライン';
        $_css = ['room_list', 'site_header', 'site_footer'];

        // ページネーションのselect要素
        [$title, $_select, $_label] = $this->pagination->geneSelectElementPagerAsc(
            $path,
            '',
            $unmodifeidPageNumber,
            $rankingList['totalRecords'],
            AppConfig::LIST_LIMIT_RECENT_COMMENT,
            $rankingList['maxPageNumber'],
            $rankingList['labelArray'],
            true
        );

        $subTitle = $unmodifeidPageNumber === 0 ? ' - 最新50件' : " - {$unmodifeidPageNumber}ページ目";
        $_meta = meta()->setTitle($pageTitle . $subTitle);

        $_breadcrumbsShema = $this->breadcrumbsShema->generateSchema(
            'コメント',
            'comments-timeline',
            $subTitle,
        );

        $topPageDto = $staticDataGeneration->getTopPageData();

        return view(
            'recent_comment',
            compact(
                '_meta',
                '_css',
                '_select',
                '_label',
                'path',
                '_breadcrumbsShema',
                'topPageDto',
                'unmodifeidPageNumber',
            ) + $rankingList
        );
    }
}

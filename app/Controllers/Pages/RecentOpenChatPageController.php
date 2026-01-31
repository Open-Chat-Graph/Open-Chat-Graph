<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Views\OpenChatStatisticsRecent;
use App\Config\AppConfig;
use App\Services\Admin\AdminAuthService;
use App\Views\Schema\PageBreadcrumbsListSchema;
use App\Views\SelectElementPagination;
use Shadow\Kernel\Reception;

class RecentOpenChatPageController
{
    function __construct(
        private OpenChatStatisticsRecent $openChatStatsRecent,
        private SelectElementPagination $pagination,
        private PageBreadcrumbsListSchema $breadcrumbsShema
    ) {}
    
    function index(AdminAuthService $adminAuthService)
    {
        $recentPage = Reception::input('page');
        $rankingList = $this->openChatStatsRecent->getAllOrderByRegistrationDate(
            $recentPage,
            AppConfig::LIST_LIMIT_RECENTLY_REGISTERED
        );

        if (!$rankingList) {
            // 最大ページ数を超えてる場合は404
            return false;
        }

        $path = 'recently-registered';
        $pageTitle = 'オプチャグラフに最近登録されたオープンチャット';
        $_css = ['room_list', 'site_header', 'site_footer'];

        $isAdmin = $adminAuthService->auth();

        // ページネーションのselect要素
        [$title, $_select, $_label] = $this->pagination->geneSelectElementPagerAsc(
            $path,
            '',
            $rankingList['pageNumber'],
            $rankingList['totalRecords'],
            AppConfig::LIST_LIMIT_RECENTLY_REGISTERED,
            $rankingList['maxPageNumber'],
            $rankingList['labelArray']
        );

        $subTitle = $recentPage === 0 ? '' : "({$recentPage}ページ目)";
        $_meta = meta()->setTitle($pageTitle . $subTitle);

        $_breadcrumbsShema = $this->breadcrumbsShema->generateSchema('最近登録されたオープンチャット');

        return view(
            'recent_content',
            compact('_meta', '_css', '_select', '_label', 'path', 'isAdmin', '_breadcrumbsShema') + $rankingList
        );
    }
}

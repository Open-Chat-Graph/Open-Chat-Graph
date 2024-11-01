<?php

declare(strict_types=1);

namespace App\Views;

use App\Config\AppConfig;
use App\Models\CommentRepositories\RecentCommentListRepositoryInterface;
use App\Services\Traits\TraitPaginationRecordsCalculator;

class RecentComment
{
    use TraitPaginationRecordsCalculator;

    public function __construct(
        private RecentCommentListRepositoryInterface $recentCommentListRepository,
        private OpenChatPagination $openChatPagination,
    ) {}

    /**
     * @return array|false `['pageNumber' => int, 'maxPageNumber' => int, 'openChatList' => array, 'totalRecords' => int, 'labelArray' => array]`
     */
    public function getAllOrderByRegistrationDate(int $pageNumber): array|false
    {
        //$labelArray = $this->openChatListRepository->findAllOrderByIdCreatedAtColumn();

        return $this->openChatPagination->getSelectElementArgOrderDesc(
            $pageNumber,
            $this->recentCommentListRepository->getRecordCount(),
            fn(int $offset, int $limit) => $this->recentCommentListRepository
                ->findRecentCommentOpenChatAll($offset, $limit, order: 'ASC'),
            limit: AppConfig::RECENT_COMMENT_LIST_LIMIT,
        );
    }
}

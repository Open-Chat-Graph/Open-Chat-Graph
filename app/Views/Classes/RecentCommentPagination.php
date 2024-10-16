<?php

declare(strict_types=1);

namespace App\Views;

use App\Models\CommentRepositories\RecentCommentListRepositoryInterface;
use App\Services\Traits\TraitPaginationRecordsCalculator;

class RecentCommentPagination
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
            count($labelArray),
            $this->openChatListRepository->findAllOrderById(...),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Views;

use App\Models\Repositories\OpenChatRecentListRepositoryInterface;
use App\Services\Traits\TraitPaginationRecordsCalculator;

class OpenChatStatisticsRecent
{
    use TraitPaginationRecordsCalculator;

    public function __construct(
        private OpenChatRecentListRepositoryInterface $openChatListRepository,
        private OpenChatPagination $openChatPagination,
    ) {}

    /**
     * @return array|false `['pageNumber' => int, 'maxPageNumber' => int, 'openChatList' => array, 'totalRecords' => int, 'labelArray' => array]`
     */
    public function getAllOrderByRegistrationDate(int $pageNumber, int $limit): array|false
    {
        $labelArray = $this->openChatListRepository->findAllOrderByIdCreatedAtColumn();

        return $this->openChatPagination->getSelectElementArgOrderDesc(
            $pageNumber,
            count($labelArray),
            $this->openChatListRepository->findAllOrderByEntity(...),
            $labelArray,
            $limit,
        );
    }
}

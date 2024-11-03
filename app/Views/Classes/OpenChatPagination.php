<?php

declare(strict_types=1);

namespace App\Views;

use App\Services\Traits\TraitPaginationRecordsCalculator;
use App\Config\AppConfig;

class OpenChatPagination
{
    use TraitPaginationRecordsCalculator;

    /**
     * @param \Closure $repository `function(int $startId, int $endId): array`
     * 
     * @return array|false `['pageNumber' => int, 'maxPageNumber' => int, 'openChatList' => array, 'totalRecords' => int, 'labelArray' => array]`
     */
    function getSelectElementArgOrderDesc(
        int $pageNumber,
        int $totalRecords,
        \Closure $repository,
        array $labelArray,
        int $limit
    ): array|false {
        // ページの最大数を取得する
        $maxPageNumber = $this->calcMaxPages($totalRecords, $limit);

        if (!$pageNumber) {
            $pageNumber = $maxPageNumber;
        }

        if ($pageNumber > $maxPageNumber) {
            // 現在のページ番号が最大ページ番号を超えている場合
            return false;
        }

        $repoArgs = [$this->calcOffset($pageNumber, $limit), $limit * $pageNumber];

        // リストを取得する
        $openChatList = array_reverse($repository(...$repoArgs));

        return compact(
            'pageNumber',
            'maxPageNumber',
            'openChatList',
            'totalRecords',
            'labelArray',
        );
    }
}

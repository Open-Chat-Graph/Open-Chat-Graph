<?php

declare(strict_types=1);

namespace App\Views;

use App\Services\Traits\TraitPaginationRecordsCalculator;
use App\Config\AppConfig;

class OpenChatPagination
{
    use TraitPaginationRecordsCalculator;

    /**
     * @param \Closure $repository `function(int $startId, int $endId, int $totalRecords): array`
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
        // totalRecordsが0の場合は空の結果を返す
        if ($totalRecords === 0) {
            return [
                'pageNumber' => 1,
                'maxPageNumber' => 1,
                'openChatList' => [],
                'totalRecords' => 0,
                'labelArray' => [],
            ];
        }

        // ページの最大数を取得する
        $maxPageNumber = $this->calcMaxPages($totalRecords, $limit);

        $unmodifeidPageNumber = $pageNumber;
        if (!$pageNumber) {
            $pageNumber = $maxPageNumber;
        }

        if ($pageNumber > $maxPageNumber) {
            // 現在のページ番号が最大ページ番号を超えている場合
            return false;
        }

        $repoArgs = [
            $unmodifeidPageNumber === 0 ? max(0, $totalRecords - $limit) : $this->calcOffset($pageNumber, $limit),
            $limit * $pageNumber
        ];

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

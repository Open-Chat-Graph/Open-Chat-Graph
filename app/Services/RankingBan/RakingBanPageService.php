<?php

declare(strict_types=1);

namespace App\Services\RankingBan;

use App\Services\Traits\TraitPaginationRecordsCalculator;
use App\Models\RankingBanRepositories\RankingBanPageRepository;
use App\Services\RankingBan\Dto\RankingBanPageDto;

class RakingBanPageService
{
    use TraitPaginationRecordsCalculator;

    public function __construct(
        private RankingBanPageRepository $rankingBanPageRepository
    ) {
    }

    /**
     * @param int $publish 0:掲載中のみ, 1:未掲載のみ, 2:すべて
     * @param int $change 0:内容変更ありのみ, 1:変更なしのみ, 2:すべて
     * @param string $since 消えた日の開始 YYYY-MM-DD（検証済み・空文字なら条件なし）
     * @param string $until 消えた日の終了 YYYY-MM-DD（同上）
     * @return RankingBanPageDto|null ページ範囲外なら null
     */
    public function getAllOrderByDateTime(int $change, int $publish, int $percent, string $keyword, int $pageNumber, int $limit, string $since = '', string $until = ''): ?RankingBanPageDto
    {
        $labelArray = $this->rankingBanPageRepository->findAllDatetimeColumn($change, $publish, $percent, $keyword, $since, $until);

        // ページの最大数を取得する
        $totalRecords = count($labelArray);
        $maxPageNumber = $this->calcMaxPages($totalRecords, $limit);
        if ($pageNumber > $maxPageNumber) {
            // 現在のページ番号が最大ページ番号を超えている場合
            return null;
        }

        // リストを取得する
        $list = $this->rankingBanPageRepository->findAllOrderByIdDesc(
            $change,
            $publish,
            $percent,
            $keyword,
            $this->calcOffset($pageNumber, $limit),
            $limit,
            $since,
            $until
        );

        $openChatList = array_map(function ($oc) {
            if (!$oc['update_items']) return $oc;
            $oc['update_items'] = array_keys(
                array_filter(json_decode($oc['update_items'], true))
            );
            return $oc;
        }, $list);

        return new RankingBanPageDto(
            pageNumber: $pageNumber,
            maxPageNumber: $maxPageNumber,
            openChatList: $openChatList,
            totalRecords: $totalRecords,
            labelArray: $labelArray,
        );
    }
}

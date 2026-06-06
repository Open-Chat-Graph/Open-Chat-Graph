<?php

declare(strict_types=1);

namespace App\Services\RankingBan\Dto;

/**
 * 掲載分析（labs/publication-analytics）の 1 ページ分の結果。
 *
 * @see \App\Services\RankingBan\RakingBanPageService::getAllOrderByDateTime()
 */
class RankingBanPageDto
{
    /**
     * @param array     $openChatList 表示用ルーム行（components/open_chat_list_ranking_ban が描画）
     * @param string[]  $labelArray   ページャ生成用の「消えた日時」一覧（降順）
     */
    function __construct(
        public int $pageNumber,
        public int $maxPageNumber,
        public array $openChatList,
        public int $totalRecords,
        public array $labelArray,
    ) {}
}

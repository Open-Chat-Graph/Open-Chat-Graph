<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Services\RankingBan\RakingBanPageService;
use App\Services\Storage\FileStorageInterface;
use App\Views\RankingBanSelectElementPagination;

class RankingBanLabsPageController
{
    /**
     * シェル（外枠）を即時返却する。重い一覧データは fragment() が後追いで返す。
     */
    function index(
        FileStorageInterface $fileStorage,
        int $change,
        int $publish,
        int $percent,
        int $page,
        string $keyword
    ) {
        $titleValue = $this->buildTitleValue($publish, $change, $percent, $keyword);

        $_meta = meta()
            ->setTitle('オプチャ公式ランキング掲載の分析 ' . ($page > 1 ? "({$page}ページ目) " : '') . $titleValue)
            ->setDescription(
                'オプチャ公式ランキングへの掲載・未掲載の状況を一覧表示します。ルーム内容の変更後などに起こる掲載状況（検索落ちなど）の変動を捉えることができます。'
            );

        $_meta->image_url = '';

        $_css = ['room_list', 'site_header', 'site_footer', 'ranking_ban'];

        $_updatedAt = new \DateTime($fileStorage->getContents('@hourlyRealUpdatedAtDatetime'));
        $_now = $fileStorage->getContents('@hourlyCronUpdatedAtDatetime');

        return view(
            'ranking_ban_content',
            compact(
                '_meta',
                '_css',
                '_updatedAt',
                '_now',
                'titleValue',
            )
        );
    }

    /**
     * 一覧データのHTMLフラグメントを返す（JSがfetchして差し込む）。
     * クエリ・絞り込みロジックは旧 index() からの移設で、意味は不変。
     */
    function fragment(
        RakingBanPageService $rakingBanPageService,
        RankingBanSelectElementPagination $rankingBanSelectElementPagination,
        FileStorageInterface $fileStorage,
        int $change,
        int $publish,
        int $percent,
        int $page,
        string $keyword
    ) {
        header('X-Robots-Tag: noindex');

        $titleValue = $this->buildTitleValue($publish, $change, $percent, $keyword);

        $_now = $fileStorage->getContents('@hourlyCronUpdatedAtDatetime');

        $limit = 50;

        $rankingBanData = $rakingBanPageService->getAllOrderByDateTime(
            $change,
            $publish,
            $percent,
            $keyword,
            $page,
            $limit
        );

        if (!$rankingBanData && $page > 1) return false;
        if (!$rankingBanData && $page === 1) {
            $totalRecords = 0;
            $maxPageNumber = 0;
            return view(
                'components/ranking_ban_results',
                compact(
                    '_now',
                    'totalRecords',
                    'maxPageNumber',
                    'page',
                    'titleValue',
                )
            );
        }

        $totalRecords = $rankingBanData['totalRecords'];
        $maxPageNumber = $rankingBanData['maxPageNumber'];
        $path = 'labs/publication-analytics';
        $params = compact('change', 'publish', 'percent', 'keyword');

        [$title, $_select, $_label] = $rankingBanSelectElementPagination->geneSelectElementPagerAsc(
            $path,
            $params,
            $page,
            $totalRecords,
            $limit,
            $rankingBanData['maxPageNumber'],
            array_reverse($rankingBanData['labelArray'])
        );

        $openChatList =  $rankingBanData['openChatList'];
        $_pagerNavArg = [
            'path' => $path,
            'params' => $params,
            'pageNumber' => $page,
            'maxPageNumber' => $maxPageNumber
        ];

        return view(
            'components/ranking_ban_results',
            compact(
                'openChatList',
                '_now',
                '_select',
                '_label',
                '_pagerNavArg',
                'totalRecords',
                'maxPageNumber',
                'page',
                'titleValue',
            )
        );
    }

    /**
     * meta title・フラグメントの data-title 用ラベル（既存仕様のままパリティ維持）
     */
    private function buildTitleValue(int $publish, int $change, int $percent, string $keyword): string
    {
        return implode(' ', array_filter([
            'p' => $publish === 1 ? '💡現在未掲載' : ($publish === 0 ? '💡再掲載済み' : '💡全て'),
            'c' => $change === 1 ? '📝ルーム内容変更なし' : ($change === 0 ? '📝ルーム内容変更あり' : '📝全て'),
            'per' => $percent < 100 ? "📊ランク上位{$percent}%" : '📊全て',
            'keyword' => $keyword !== '' ? "\n🔎「{$keyword}」" : false,
        ]));
    }
}

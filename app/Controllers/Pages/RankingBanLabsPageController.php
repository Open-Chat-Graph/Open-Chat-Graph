<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Services\RankingBan\RakingBanPageService;
use App\Services\Storage\FileStorageInterface;
use App\Views\RankingBanSelectElementPagination;
use Shadow\Kernel\ViewInterface;

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
        int $dmin,
        int $dmax,
        int $page,
        string $keyword,
        string $since,
        string $until
    ): ViewInterface {
        $since = $this->validDate($since);
        $until = $this->validDate($until);
        [$dmin, $dmax] = $this->normalizeDuration($dmin, $dmax);

        $titleValue = $this->buildTitleValue($publish, $change, $percent, $keyword, $since, $until, $dmin, $dmax);

        $_meta = meta()
            ->setTitle('オプチャ公式ランキング掲載の分析 ' . ($page > 1 ? "({$page}ページ目) " : '') . $titleValue)
            ->setDescription(
                'オプチャ公式ランキングへの掲載・未掲載の状況を一覧表示します。ルーム内容の変更後などに起こる掲載状況（検索落ちなど）の変動を捉えることができます。'
            );

        $_meta->image_url = '';

        $_css = ['components/room_list', 'components/site_header', 'components/site_footer', 'pages/ranking_ban'];

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
                'since',
                'until',
                'dmin',
                'dmax',
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
        int $dmin,
        int $dmax,
        int $page,
        string $keyword,
        string $since,
        string $until
    ): ViewInterface|false {
        header('X-Robots-Tag: noindex');

        $since = $this->validDate($since);
        $until = $this->validDate($until);
        [$dmin, $dmax] = $this->normalizeDuration($dmin, $dmax);

        $titleValue = $this->buildTitleValue($publish, $change, $percent, $keyword, $since, $until, $dmin, $dmax);

        $_now = $fileStorage->getContents('@hourlyCronUpdatedAtDatetime');

        $limit = 50;

        $rankingBanData = $rakingBanPageService->getAllOrderByDateTime(
            $change,
            $publish,
            $percent,
            $keyword,
            $page,
            $limit,
            $since,
            $until,
            $dmin,
            $dmax,
            $_now
        );

        if ($rankingBanData === null && $page > 1) return false;
        if ($rankingBanData === null) {
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
                    'percent',
                    'dmin',
                    'dmax',
                )
            );
        }

        $totalRecords = $rankingBanData->totalRecords;
        $maxPageNumber = $rankingBanData->maxPageNumber;
        $path = 'labs/publication-analytics';
        // クエリ順は JS 側 buildQuery と同一に保つ（CDNキャッシュキーの分裂防止）
        $params = compact('change', 'publish', 'percent', 'keyword', 'since', 'until', 'dmin', 'dmax');

        [$title, $_select, $_label] = $rankingBanSelectElementPagination->geneSelectElementPagerAsc(
            $path,
            $params,
            $page,
            $totalRecords,
            $limit,
            $maxPageNumber,
            array_reverse($rankingBanData->labelArray)
        );

        $openChatList = $rankingBanData->openChatList;
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
    private function buildTitleValue(int $publish, int $change, int $percent, string $keyword, string $since = '', string $until = '', int $dmin = 0, int $dmax = 0): string
    {
        return implode(' ', array_filter([
            'p' => $publish === 1 ? '💡現在未掲載' : ($publish === 0 ? '💡再掲載済み' : '💡全て'),
            'c' => $change === 1 ? '📝ルーム内容変更なし' : ($change === 0 ? '📝ルーム内容変更あり' : '📝全て'),
            'per' => $percent < 100 ? "📊ランク上位{$percent}%" : '📊全て',
            'dur' => ($dmin > 0 || $dmax > 0) ? '⏳消えていた期間：' . $this->durationLabel($dmin, $dmax) : false,
            'd' => ($since !== '' || $until !== '') ? "📅{$since}〜{$until}" : false,
            'keyword' => $keyword !== '' ? "\n🔎「{$keyword}」" : false,
        ]));
    }

    /**
     * 消えていた期間（時間単位の下限・上限）の正規化。矛盾した範囲は下限を優先して上限を捨てる。
     */
    private function normalizeDuration(int $dmin, int $dmax): array
    {
        if ($dmin > 0 && $dmax > 0 && $dmin >= $dmax) return [$dmin, 0];
        return [$dmin, $dmax];
    }

    /**
     * 消えていた期間の表示ラベル。チップと同じ区分は同じ言葉、それ以外は時間/日で組み立てる。
     * JS側 durLabel() と同一仕様（パリティ維持）。
     */
    private function durationLabel(int $dmin, int $dmax): string
    {
        $known = ['0-24' => '24時間以内', '24-72' => '1〜3日', '72-168' => '3〜7日', '168-0' => '1週間以上', '72-0' => '3日以上'];
        $key = "{$dmin}-{$dmax}";
        if (isset($known[$key])) return $known[$key];

        $fmt = fn (int $h): string => $h % 24 === 0 ? ($h / 24) . '日' : $h . '時間';
        if ($dmin > 0 && $dmax > 0) return $fmt($dmin) . '〜' . $fmt($dmax);
        if ($dmin > 0) return $fmt($dmin) . '以上';
        return $fmt($dmax) . '以内';
    }

    /**
     * 期間入力の検証。YYYY-MM-DD の実在日付のみ通し、それ以外は空文字（条件なし）に落とす。
     */
    private function validDate(string $value): string
    {
        if ($value === '') return '';
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return ($d && $d->format('Y-m-d') === $value) ? $value : '';
    }
}

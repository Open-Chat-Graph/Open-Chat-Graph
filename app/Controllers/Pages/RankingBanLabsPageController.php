<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Services\RankingBan\RakingBanPageService;
use App\Services\Storage\FileStorageInterface;
use App\Views\RankingBanSelectElementPagination;
use Shadow\Kernel\ViewInterface;

class RankingBanLabsPageController
{
    /** 「ルームの変更内容」で絞り込めるキー（正準順）。update_items の JSON キーと一致させる */
    private const UPDATE_ITEM_KEYS = ['name', 'description', 'img_url', 'join_method_type', 'category', 'emblem'];

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
        string $until,
        string $items
    ): ViewInterface {
        $since = $this->validDate($since);
        $until = $this->validDate($until);
        [$dmin, $dmax] = $this->normalizeDuration($dmin, $dmax);
        $selectedItems = $this->normalizeItems($items);

        $titleValue = $this->buildTitleValue($publish, $change, $percent, $keyword, $since, $until, $dmin, $dmax, $selectedItems);

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
                'selectedItems',
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
        string $until,
        string $items
    ): ViewInterface|false {
        // noindex: フラグメント自体は検索結果に出さない。nofollow: ページャの深いページリンクを
        // クローラに辿らせない（cf.client.bot はCFヘッダゲートを通れるため、深いOFFSETの巡回を抑止）
        header('X-Robots-Tag: noindex, nofollow');

        $since = $this->validDate($since);
        $until = $this->validDate($until);
        [$dmin, $dmax] = $this->normalizeDuration($dmin, $dmax);
        $itemsArr = $this->normalizeItems($items);
        $items = implode(',', $itemsArr); // 正準化した文字列（ページャのリンク・CDNキャッシュキー用）

        $titleValue = $this->buildTitleValue($publish, $change, $percent, $keyword, $since, $until, $dmin, $dmax, $itemsArr);

        $_now = $fileStorage->getContents('@hourlyCronUpdatedAtDatetime');

        $limit = 200;

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
            $_now,
            $itemsArr
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
        $params = compact('change', 'items', 'publish', 'percent', 'keyword', 'since', 'until', 'dmin', 'dmax');

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
    private function buildTitleValue(int $publish, int $change, int $percent, string $keyword, string $since = '', string $until = '', int $dmin = 0, int $dmax = 0, array $items = []): string
    {
        return implode(' ', array_filter([
            'p' => $publish === 1 ? '💡現在未掲載' : ($publish === 0 ? '💡再掲載済み' : '💡全て'),
            'c' => $change === 1 ? '📝ルーム内容変更なし' : ($change === 0 ? '📝ルーム内容変更あり' : '📝全て'),
            'ci' => $items ? '🏷️' . implode('・', array_map([$this, 'itemLabel'], $items)) : false,
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
     * 「ルームの変更内容」フィルタの正規化。カンマ区切り入力を許可キーのみ・正準順に整える。
     * 値は LIKE パターン（"key":true）の組み立てに使うため、ホワイトリスト外のキーは捨てる。
     *
     * @return list<string> 許可キー（name/description/img_url/join_method_type/category/emblem）
     */
    private function normalizeItems(string $items): array
    {
        if ($items === '') return [];
        $selected = explode(',', $items);
        return array_values(array_filter(
            self::UPDATE_ITEM_KEYS,
            fn (string $key) => in_array($key, $selected, true)
        ));
    }

    /**
     * 変更内容キーの表示ラベル（meta title 用）。一覧カード(open_chat_list_ranking_ban)と同じ対応。
     */
    private function itemLabel(string $key): string
    {
        return [
            'name' => 'ルーム名',
            'description' => '説明文',
            'img_url' => '画像',
            'join_method_type' => '公開設定',
            'category' => 'カテゴリー',
            'emblem' => 'バッジ',
        ][$key] ?? $key;
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

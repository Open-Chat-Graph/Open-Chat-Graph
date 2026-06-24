<?php

declare(strict_types=1);

namespace App\Views;

class RankingBanSelectElementPagination
{
    static function pagerUrl(string $path, int $pageNumber, array $params): string
    {
        if ($pageNumber > 1) $params['page'] = $pageNumber;

        return \Shadow\Kernel\Dispatcher\ReceptionInitializer::getDomainAndHttpHost()
            . '/' . $path . '?' . http_build_query($params);
    }

    private function formatDateTimeHourly(string $dateTimeStr): string
    {
        // 引数の日時をDateTimeオブジェクトに変換
        $dateTime = new \DateTime($dateTimeStr);

        // 現在の年を取得
        $currentYear = date("Y");

        // 引数の日時の年を取得
        $yearOfDateTime = $dateTime->format("Y");

        // 現在の年と引数の日時の年を比較
        if ($yearOfDateTime == $currentYear) {
            // 今年の場合のフォーマット
            return $dateTime->format("m/d H時");
        } else {
            // 今年以外の場合のフォーマット
            return $dateTime->format("Y/m/d H時");
        }
    }

    /**
     * 各ページ「最新→最古」の境界日付つき select 要素を生成する。
     *
     * $labelArray は降順（新しい順）の境界日付配列で、長さは「表示上限ページ×件数」まで。
     * ページ i の先頭（最新）= $labelArray[(i-1)*件数]、末尾（最古）= $labelArray[min(i*件数, 件数)-1] を参照する。
     *
     * @param string[] $labelArray 降順の「消えた日時」一覧（表示上限ページ分まで）
     * @return array `['', $_selectElement, $_label]`（先頭の title は未使用）
     */
    function geneSelectElementPagerAsc(string $pagePath, array $params, int $pageNumber, int $itemsPerPage, int $maxPage, array $labelArray = []): array
    {
        $count = count($labelArray);

        // ページ i の「最新（先頭）→最古（末尾）」の境界日付ラベルを返す
        $rangeLabel = function (int $i) use ($labelArray, $itemsPerPage, $count): array {
            $startIdx = ($i - 1) * $itemsPerPage;            // そのページの先頭（最新）
            $endIdx = min($i * $itemsPerPage, $count) - 1;   // そのページの末尾（最古）
            $start = isset($labelArray[$startIdx]) ? $this->formatDateTimeHourly($labelArray[$startIdx]) : '';
            $end = isset($labelArray[$endIdx]) ? $this->formatDateTimeHourly($labelArray[$endIdx]) : '';
            return [$start, $end];
        };

        $_selectElement = '';
        for ($i = 1; $i <= $maxPage; $i++) {
            [$start, $end] = $rangeLabel($i);
            $selected = ($i === $pageNumber) ? "selected='selected'" : '';
            $url = $this->pagerUrl($pagePath, $i, $params);
            $_selectElement .= "<option value='{$url}' {$selected}>{$start} → {$end} ({$i}ページ目)</option>\n";
        }

        [$curStart, $curEnd] = $rangeLabel($pageNumber);
        $_label = "{$curStart} → {$curEnd}<br>({$pageNumber}ページ目)";

        // 先頭要素（旧 title）は呼び出し側で未使用
        return ['', $_selectElement, $_label];
    }
}

<?php

declare(strict_types=1);

namespace App\Views;

class RankingBanSelectElementPagination
{
    static function pagerUrl(string $path, int $pageNumber, array $params): string
    {
        if ($pageNumber > 1) $params['page'] = $pageNumber;

        return getDomainAndHttpHost()
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
     * 昇順ページネーションのselect要素を生成
     * 
     * @return array `[$title, $_selectElement, $_label]`
     */
    function geneSelectElementPagerAsc(string $pagePath, array $params, int $pageNumber, int $totalRecords, int $itemsPerPage, int $maxPage, array $labelArray = []): array
    {
        // ページ番号の表示に必要な要素を取得する
        $getElement = function ($url, $selected, $start, $end, $i) use ($labelArray) {
            $startLabel = isset($labelArray[$start - 1]) ? $this->formatDateTimeHourly($labelArray[$start - 1]) : '';
            $endLabel = isset($labelArray[$end - 1]) ? $this->formatDateTimeHourly($labelArray[$end - 1]) : '';

            return "<option value='{$url}' {$selected}>{$startLabel} → {$endLabel} ({$i}ページ目)</option>";
        };

        // 選択されたページに対して"selected"属性を返す
        $selected = fn($i) => ($i === $pageNumber) ? "selected='selected'" : '';

        // ページ番号に応じて、そのページの最初のインデックスを計算する
        $startNum = fn($i) => ($i === 1) ? $totalRecords : $totalRecords - (($i - 1) * $itemsPerPage);

        // ページ番号に応じて、そのページの最後のインデックスを計算する
        $endNum = fn($i) => ($i === $maxPage) ? 1 : $totalRecords - ($i * $itemsPerPage) + 1;

        // 各ページ番号の要素を生成する
        $_selectElement = '';
        for ($i = 1; $i <= $maxPage; $i++) {
            $_selectElement .= $getElement($this->pagerUrl($pagePath, $i, $params), $selected($i), $startNum($i), $endNum($i), $i) . "\n";
        }

        // ラベルの番号を取得する
        $labelStartNum = $startNum($pageNumber);
        $labelEndNum = $endNum($pageNumber);

        $startLabel = isset($labelArray[$labelStartNum - 1]) ? $this->formatDateTimeHourly($labelArray[$labelStartNum - 1]) : '';
        $endLabel = isset($labelArray[$labelEndNum - 1]) ? $this->formatDateTimeHourly($labelArray[$labelEndNum - 1]) : '';

        // select要素のラベルを生成する
        $_label = "{$startLabel} → {$endLabel}<br>({$pageNumber}ページ目)";

        // タイトル用の文字列
        $title = "{$labelEndNum} ~ {$labelStartNum}";

        return [$title, $_selectElement, $_label];
    }
}

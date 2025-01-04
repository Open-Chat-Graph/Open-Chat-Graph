<?php

declare(strict_types=1);

namespace App\Views;

class SelectElementPagination
{
    static function pagerUrl(string $pagePath, int $pageNumber, int $maxPage): string
    {
        $secondPath = "/{$pagePath}/" . $pageNumber;
        return url() . $secondPath;
    }

    // ページ番号の表示に必要な要素を取得する
    protected function getOptionElement($labelArray, $url, $selected, $start, $end, $i): string
    {
        $startLabel = $labelArray[$start - 1] ?? '';
        $endLabel = $labelArray[$end - 1] ?? '';

        return "<option value='{$url}' {$selected}>{$startLabel} ~ {$endLabel} ({$start} ~ {$end})</option>";
    }

    // ページ番号の表示に必要な要素を取得する
    protected function getOptionFirstElement($url, $selected, $itemsPerPage): string
    {
        return "<option value='{$url}' {$selected}>最新{$itemsPerPage}件</option>";
    }

    // select要素のラベルを生成する
    protected function getLabel(array $labelArray, int $labelStartNum, int $labelEndNum, int $pageNumber, int $itemsPerPage): string
    {
        $labelStart = $labelArray[$labelStartNum - 1] ?? '';
        $labelEnd = $labelArray[$labelEndNum - 1] ?? '';

        return $pageNumber === 0 ? "最新{$itemsPerPage}件" : "{$labelStart} ~ {$labelEnd}<br>({$labelStartNum} ~ {$labelEndNum})";
    }

    /**
     * 昇順ページネーションのselect要素を生成
     * 
     * @return array `[$title, $_selectElement, $_label]`
     */
    function geneSelectElementPagerAsc(
        string $pagePath,
        string $queryString,
        int $pageNumber,
        int $totalRecords,
        int $itemsPerPage,
        int $maxPage,
        array $labelArray = [],
        bool $showLatest = false
    ): array {
        // 選択されたページに対して"selected"属性を返す
        $selected = fn($i) => ($i === $pageNumber) ? "selected='selected'" : '';

        // ページ番号に応じて、そのページの最初のインデックスを計算する
        $startNum = fn($i) => ($i - 1) * $itemsPerPage + 1;

        // ページ番号に応じて、そのページの最後のインデックスを計算する
        $endNum = fn($i) => ($i === $maxPage) ? $totalRecords : ($i * $itemsPerPage);

        // 各ページ番号の要素を生成する
        $selectElement = [];
        for ($i = 1; $i <= $maxPage; $i++) {
            // ページ番号の表示に必要な要素を取得する
            $selectElement[] = $this->getOptionElement(
                $labelArray,
                static::pagerUrl($pagePath, $i, $maxPage) . $queryString,
                $selected($i),
                $startNum($i),
                $endNum($i),
                $i
            ) . "\n";

            if ($i === $maxPage && $showLatest) {
                $selectElement[] = $this->getOptionFirstElement(
                    url() . "/{$pagePath}" . $queryString,
                    $pageNumber === 0 ? "selected='selected'" : '',
                    $itemsPerPage
                ) . "\n";
            }
        }

        // ラベルの番号を取得する
        $labelStartNum = $startNum($pageNumber);
        $labelEndNum = $endNum($pageNumber);

        // select要素のラベルを生成する
        $_label = $this->getLabel($labelArray, $labelStartNum, $labelEndNum, $pageNumber, $itemsPerPage);

        // タイトル用の文字列
        $title = "{$labelStartNum} ~ {$labelEndNum}";

        return [$title, implode("\n", array_reverse($selectElement)), $_label];
    }
}

<?php

declare(strict_types=1);

namespace App\Views;

class RecentCommentSelectElementPagination extends SelectElementPagination
{
    // ページ番号の表示に必要な要素を取得する
    protected function getOptionElement($labelArray, $url, $selected, $start, $end, $i): string
    {
        return "<option value='{$url}' {$selected}>{$i}ページ({$start}-{$end}コメント)</option>";
    }

    // select要素のラベルを生成する
    protected function getLabel(array $labelArray, int $labelStartNum, int $labelEndNum, int $pageNumber): string
    {
        return "{$pageNumber}ページ({$labelStartNum}-{$labelEndNum}コメント)";
    }
}

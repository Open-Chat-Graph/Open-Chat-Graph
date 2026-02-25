<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Services\Recommend\Dto\RecommendListDto;

interface BulkRecommendRankingBuilderInterface
{
    /**
     * 事前取得データを受け取り、インデックスを構築する
     *
     * @param array<int, array> $allData IDをキーとした連想配列
     */
    function init(array $allData): void;

    /**
     * タグ別ランキングを構築する
     */
    function buildTagRanking(string $tag, string $listName): RecommendListDto;

    /**
     * カテゴリ別ランキングを構築する
     */
    function buildCategoryRanking(int $category, string $listName): RecommendListDto;

    /**
     * 公式ルーム別ランキングを構築する
     */
    function buildOfficialRanking(int $emblem, string $listName): RecommendListDto;
}

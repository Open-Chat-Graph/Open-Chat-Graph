<?php

declare(strict_types=1);

namespace App\Models\RecommendRepositories;

interface BulkRankingDataRepositoryInterface
{
    /**
     * 全オープンチャットデータを1クエリで取得する
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     img_url: string,
     *     api_img_url: string,
     *     member: string,
     *     description: string,
     *     emblem: string,
     *     category: string,
     *     emid: string,
     *     url: string,
     *     api_created_at: string,
     *     created_at: string,
     *     updated_at: string,
     *     join_method_type: string,
     *     recommend_tag: ?string,
     *     oc_tag: ?string,
     *     oc_tag2: ?string,
     *     hour_diff: ?string,
     *     hour24_diff: ?string,
     *     week_diff: ?string,
     * }> IDをキーとした連想配列
     */
    function fetchAll(): array;
}

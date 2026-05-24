<?php

declare(strict_types=1);

namespace App\Models\Repositories;

/**
 * /oc/{id} narrative セクション生成に必要なデータ取得 Interface。
 *
 * 内部で OHLC Repository を組み合わせ、Service 層を時系列クエリ実装から切り離す。
 */
interface OcNarrativeRepositoryInterface
{
    /**
     * narrative 用のメンバー数メトリクス（OHLC 集約）。
     *
     * @return array{
     *     curr: ?int,
     *     curr_date: ?string,
     *     m7: ?int,
     *     m30: ?int,
     *     m90: ?int,
     *     sample_n: int,
     *     peak_high: ?int,
     *     peak_date: ?string,
     *     max_single_day_growth: ?int,
     *     max_growth_date: ?string,
     *     first_date: ?string
     * }
     */
    public function getMemberMetrics(int $openChatId): array;

    /**
     * 直近 N 日のカテゴリ内 (Ranking type) 順位推移サマリ。
     *
     * @return array{
     *     oldest_close: ?int,
     *     oldest_date: ?string,
     *     latest_close: ?int,
     *     latest_date: ?string,
     *     best_high: ?int,
     *     sample_n: int
     * }
     */
    public function getPositionMovement(int $openChatId, int $category, int $days = 30): array;
}

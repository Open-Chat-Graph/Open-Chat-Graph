<?php

declare(strict_types=1);

namespace App\Models\Repositories\RankingPosition;

interface RankingPositionRepositoryInterface
{
    /**
     * @param array{ open_chat_id: int, category: int, position: int, time: stirng }[] $rankingHourArray
     * @param string $date Y-m-d
     */
    public function insertDailyRankingPosition(array $rankingHourArray, string $date): int;

    /**
     * @param array{ open_chat_id: int, category: int, position: int, time: stirng }[] $risingHourArray
     * @param string $date Y-m-d
     */
    public function insertDailyRisingPosition(array $risingHourArray, string $date): int;

    /**
     * @param array{ category: int, total_count_rising: int, total_count_ranking: int, time: string } $totalCount
     */
    public function insertTotalCount(array $totalCount): int;

    public function deleteDailyPosition(int $open_chat_id): void;

    /**
     * @return string|false Y-m-d
     */
    public function getLastDate(): string|false;

    /**
     * ランキング種別(ranking/rising)×カテゴリ(in/all)毎の掲載日数を
     * 全期間・指定日以降（週/月ウィンドウ）でまとめて取得する。
     *
     * @param int $inCategory カテゴリ内判定に使うカテゴリID（カテゴリ無しの部屋は -1 等の不一致値を渡す）
     * @param string $weekStartDate `Y-m-d` 週ウィンドウの開始日
     * @param string $monthStartDate `Y-m-d` 月ウィンドウの開始日
     * @return array{
     *   ranking_in: array{week:int,month:int,all:int}, ranking_all: array{week:int,month:int,all:int},
     *   rising_in: array{week:int,month:int,all:int}, rising_all: array{week:int,month:int,all:int},
     *   rising_all_week_best_pos: int|null
     * } rising_all_week_best_pos は「すべて」急上昇で週内に到達した最良順位（同一クエリに相乗り・top5判定用、無掲載は null）
     */
    public function getPositionCountsByPeriod(
        int $open_chat_id,
        int $inCategory,
        string $weekStartDate,
        string $monthStartDate
    ): array;
}

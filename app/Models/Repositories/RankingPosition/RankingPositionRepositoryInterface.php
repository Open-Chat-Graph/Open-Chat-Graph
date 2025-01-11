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
}

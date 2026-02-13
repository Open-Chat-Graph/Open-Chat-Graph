<?php

declare(strict_types=1);

namespace App\Models\Repositories;

interface AllRoomStatsRepositoryInterface
{
    public function getTotalRoomCount(): int;

    public function getTotalMemberCount(): int;

    public function getTrackingStartDate(): ?string;

    public function getNewRoomCountSince(string $interval): int;

    public function getEarliestDeletedDate(): ?string;

    public function getDeletedRoomCount(): int;

    public function getDeletedRoomCountSince(string $interval): int;

    /**
     * @return array{ category: int, room_count: int, total_members: int }[]
     */
    public function getCategoryStats(): array;

    public function getHourlyMemberIncrease(): int;

    public function getDailyMemberIncrease(): int;

    public function getWeeklyMemberIncrease(): int;

    public function getDeletedMemberCountTotal(): int;

    public function getDeletedMemberCountSince(string $phpInterval): int;
}

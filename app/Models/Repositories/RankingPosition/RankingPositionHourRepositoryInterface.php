<?php

declare(strict_types=1);

namespace App\Models\Repositories\RankingPosition;

use App\Services\OpenChat\Enum\RankingType;

interface RankingPositionHourRepositoryInterface
{
    /**
     * @param RankingPositionHourInsertDto[] $insertDtoArray
     */
    public function insertFromDtoArray(RankingType $type, string $fileTime, array $insertDtoArray): int;

    public function insertHourMemberFromDtoArray(string $fileTime, array $insertDtoArray): int;

    /**
     * @return array{ open_chat_id: int, member: int, date: string }[]
     */
    public function getDailyMemberStats(\DateTime $todayLastTime): array;

    /**
     * @return array{ open_chat_id: int, open_member: int, high_member: int, low_member: int, close_member: int, date: string }[]
     */
    public function getDailyMemberOhlc(\DateTime $todayLastTime): array;

    /**
     * @return array{ open_chat_id: int, member: int }[]
     */
    public function getHourlyMemberColumn(\DateTime $lastTime): array;

    /**
     * @return array{ open_chat_id: int, category: int, position: int, time: stirng }[]
     */
    public function getDaliyRanking(\DateTime $date, bool $all = false): array;

    /**
     * @return array{ open_chat_id: int, category: int, position: int, time: stirng }[]
     */
    public function getDailyRising(\DateTime $date, bool $all = false): array;

    /**
     * @return array{ category: int, total_count_rising: int, total_count_ranking: int, time: string }
     */
    public function getTotalCount(\DateTime $date, bool $isDate = true): array;

    public function delete(\DateTime $dateTime): void;

    /**
     * @return array{total_count_all_category_rising:int, total_count_all_category_ranking:int}
     */
    public function insertTotalCount(string $fileTime): array;

    /**
     * 指定日の毎時ランキングデータからOHLCを集約する。
     *
     * - その日にランキングに一度でも掲載されたルームのみレコードを生成する
     *   （終日圏外のルームはレコードなし）
     * - low_position: 全時間帯でランクインしていた場合は最低順位、
     *   一部の時間帯で圏外だった場合は NULL
     *
     * @return array{ open_chat_id: int, category: int, open_position: int, high_position: int, low_position: int|null, close_position: int, date: string }[]
     */
    public function getDailyPositionOhlc(RankingType $type, \DateTime $date): array;

    /**
     * @return string|false Y-m-d H:i:s
     */
    public function getLastHour(int $offset = 0): string|false;
}

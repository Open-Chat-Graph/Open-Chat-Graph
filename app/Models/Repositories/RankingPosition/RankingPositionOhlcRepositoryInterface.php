<?php

declare(strict_types=1);

namespace App\Models\Repositories\RankingPosition;

use App\Services\OpenChat\Enum\RankingType;

interface RankingPositionOhlcRepositoryInterface
{
    /**
     * @param array{ open_chat_id: int, category: int, type: string, open_position: int, high_position: int, low_position: int, close_position: int, date: string }[] $data
     */
    public function insertOhlc(array $data): int;

    /**
     * ランキング順位OHLCを日付昇順で取得する。
     *
     * - ランキングに一度も掲載されなかった日（完全に圏外）のレコードは含まれない
     * - low_position は、その日の全時間帯でランクインしていた場合は最低順位、
     *   一部の時間帯で圏外だった場合は NULL
     *
     * @return array{ date: string, open_position: int, high_position: int, low_position: int|null, close_position: int }[]
     */
    public function getOhlcDateAsc(int $open_chat_id, int $category, RankingType $type): array;
}

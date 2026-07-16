<?php

declare(strict_types=1);

namespace App\Services\PublicApi\Dto;

final readonly class ThemeResource implements \JsonSerializable
{
    public function __construct(
        public string $tag,
        public int $roomCount,
        public int $totalMembers,
        public int $change24h,
        public int $newRooms7d,
        public int $largestRoomMembers,
        public int $fastestGrowth24h,
        public string $updatedAt,
        public string $canonicalUrl,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'tag' => $this->tag,
            'room_count' => $this->roomCount,
            'total_members' => $this->totalMembers,
            'change_24h' => $this->change24h,
            'new_rooms_7d' => $this->newRooms7d,
            'largest_room_members' => $this->largestRoomMembers,
            'fastest_growth_24h' => $this->fastestGrowth24h,
            'data_updated_at' => $this->updatedAt,
            'canonical_url' => $this->canonicalUrl,
        ];
    }
}

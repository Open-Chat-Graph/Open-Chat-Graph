<?php

declare(strict_types=1);

namespace App\Services\PublicApi;

use App\Services\PublicApi\Dto\RoomResource;
use App\Services\PublicApi\Dto\ThemeResource;

final class PublicResourceFactory
{
    public function room(array $row, string $observedAt): RoomResource
    {
        $categoryId = isset($row['category']) ? (int)$row['category'] : null;
        $themes = array_values(array_unique(array_filter([
            $row['theme'] ?? null,
            $row['theme_secondary'] ?? null,
            $row['theme_tertiary'] ?? null,
        ], static fn($value) => is_string($value) && trim($value) !== '')));

        return new RoomResource(
            id: (int)$row['id'],
            name: (string)$row['name'],
            description: (string)$row['description'],
            memberCount: (int)$row['member'],
            category: $categoryId === null ? null : [
                'id' => $categoryId,
                'name' => getCategoryName($categoryId) ?: null,
            ],
            themes: $themes,
            verificationType: match ((int)($row['emblem'] ?? 0)) {
                1 => 'special',
                2 => 'official',
                default => 'none',
            },
            joinMethod: match ((int)($row['join_method_type'] ?? 0)) {
                1 => 'approval',
                2 => 'passcode',
                default => 'open',
            },
            change1h: self::nullableInt($row['change_1h'] ?? $row['ranking_change'] ?? null),
            change24h: self::nullableInt($row['change_24h'] ?? $row['ranking_change'] ?? null),
            change7d: self::nullableInt($row['change_7d'] ?? null),
            openedAt: self::timestampToRfc3339($row['api_created_at'] ?? null),
            observedAt: self::dateToRfc3339($observedAt),
            dataUpdatedAt: self::dateToRfc3339((string)($row['data_updated_at'] ?? $row['updated_at'])),
            canonicalUrl: url('oc', (string)$row['id']),
            lineTransitionUrl: url('oc', (string)$row['id'], 'jump'),
            methodologyUrl: url('policy') . '#methodology',
        );
    }

    public function theme(array $row): ThemeResource
    {
        $tag = (string)$row['tag'];
        return new ThemeResource(
            tag: $tag,
            roomCount: (int)$row['room_count'],
            totalMembers: (int)$row['total_members'],
            change24h: (int)$row['change_24h'],
            newRooms7d: (int)$row['new_rooms_7d'],
            largestRoomMembers: (int)$row['largest_room_members'],
            fastestGrowth24h: (int)$row['fastest_growth_24h'],
            updatedAt: self::dateToRfc3339((string)$row['data_updated_at']),
            canonicalUrl: url('recommend/' . urlencode($tag)),
        );
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int)$value;
    }

    private static function timestampToRfc3339(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (new \DateTimeImmutable('@' . (int)$value))->setTimezone(new \DateTimeZone('Asia/Tokyo'))->format(\DateTimeInterface::RFC3339);
        }
        return self::dateToRfc3339((string)$value);
    }

    public static function dateToRfc3339(string $value): string
    {
        return (new \DateTimeImmutable($value))->format(\DateTimeInterface::RFC3339);
    }
}

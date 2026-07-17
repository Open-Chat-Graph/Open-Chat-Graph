<?php

declare(strict_types=1);

namespace App\Models\PublicApi;

interface PublicResourceRepositoryInterface
{
    public function findRoom(int $id): array|false;
    public function isDeletedRoom(int $id): bool;
    /** @return array<int, array<string,mixed>> */
    public function listRooms(int $limit, int $offset, ?string $search, string $snapshot): array;
    public function countRooms(?string $search, string $snapshot): int;
    /** @return array<int, array<string,mixed>> */
    public function listRankings(string $period, int $category, int $limit, int $offset, string $snapshot): array;
    public function countRankings(string $period, int $category, string $snapshot): int;
    /** @return array<int, array<string,mixed>> */
    public function listThemes(int $limit, int $offset, string $snapshot): array;
    public function countThemes(string $snapshot): int;
    public function findTheme(string $tag): array|false;
    /** @return array{largest: array|false, fastest: array|false} */
    public function findThemeHighlights(string $tag): array;
    /** @return array<int, array<string,mixed>> */
    public function listThemeRooms(string $tag, int $limit, int $offset, string $snapshot): array;
    public function getSiteStats(): array;
    public function latestUpdatedAt(): string;
}

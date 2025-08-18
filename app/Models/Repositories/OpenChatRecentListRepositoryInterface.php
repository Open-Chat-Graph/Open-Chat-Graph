<?php

declare(strict_types=1);

namespace App\Models\Repositories;

interface OpenChatRecentListRepositoryInterface
{
    /**
     * @return array `[['id' => int, 'name' => string, 'url' => string, 'description' => string, 'img_url' => string, 'member' => int, 'datetime' => string]]`
     */
    public function findAllOrderByEntity(
        int $startId,
        int $endId,
    ): array;

    public function findAllOrderByIdCreatedAtColumn(): array;
}

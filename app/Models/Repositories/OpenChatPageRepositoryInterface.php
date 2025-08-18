<?php

declare(strict_types=1);

namespace App\Models\Repositories;

interface OpenChatPageRepositoryInterface
{
    public function getOpenChatById(int $id): array|false;

    public function getOpenChatByIdWithTag(int $id): array|false;

    public function isExistsOpenChat(int $id): bool;
}

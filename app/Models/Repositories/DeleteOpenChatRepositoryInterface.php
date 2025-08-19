<?php

declare(strict_types=1);

namespace App\Models\Repositories;

interface DeleteOpenChatRepositoryInterface
{
    public function deleteOpenChat(int $open_chat_id): bool;

    public function insertDeletedOpenChat(int $open_chat_id, string $emid): void;
}

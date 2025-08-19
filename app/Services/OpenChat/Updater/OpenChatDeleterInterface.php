<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Updater;

use App\Services\OpenChat\Dto\OpenChatRepositoryDto;

interface OpenChatDeleterInterface
{
    /**
     * Deletes an open chat by its ID and image URL.
     *
     * @param int $open_chat_id The ID of the open chat to delete.
     * @param string $imgUrl The URL of the image associated with the open chat.
     */
    function deleteOpenChat(OpenChatRepositoryDto $repoDto): void;
}

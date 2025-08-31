<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Api;

use App\Models\Repositories\DeleteOpenChatRepositoryInterface;
use App\Services\OpenChat\Crawler\OpenChatUrlChecker;
use App\Services\OpenChat\Dto\OpenChatRepositoryDto;
use App\Services\OpenChat\Updater\OpenChatDeleter;
use App\Services\OpenChat\Updater\OpenChatDeleterInterface;

class ApiOpenChatDeleter implements OpenChatDeleterInterface
{
    function __construct(
        private OpenChatDeleter $openChatDeleter,
        private OpenChatUrlChecker $openChatUrlChecker,
        private DeleteOpenChatRepositoryInterface $deleteOpenChatRepository,
    ) {}

    function deleteOpenChat(OpenChatRepositoryDto $repoDto): void
    {
        $this->openChatDeleter->deleteOpenChat($repoDto);

        if ($repoDto->invitationTicket && !$this->openChatUrlChecker->isOpenChatUrlAvailable($repoDto->invitationTicket)) {
            $this->deleteOpenChatRepository->insertDeletedOpenChat($repoDto->open_chat_id, '');
        }
    }
}

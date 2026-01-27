<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Updater;

use App\Services\OpenChat\Dto\OpenChatUpdaterDtoFactory;
use App\Models\Repositories\UpdateOpenChatRepositoryInterface;
use App\Services\OpenChat\Dto\OpenChatRepositoryDto;

class OpenChatDeleter implements OpenChatDeleterInterface
{
    function __construct(
        private OpenChatUpdaterDtoFactory $openChatUpdaterDtoFactory,
        private UpdateOpenChatRepositoryInterface $updateRepository,
    ) {
    }

    function deleteOpenChat(OpenChatRepositoryDto $repoDto): void
    {
        $updaterDto = $this->openChatUpdaterDtoFactory->mapToDeleteOpenChatDto($repoDto->open_chat_id);
        $this->updateRepository->updateOpenChatRecord($updaterDto);
    }
}

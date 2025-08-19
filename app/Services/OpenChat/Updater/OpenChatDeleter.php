<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Updater;

use App\Services\OpenChat\Dto\OpenChatUpdaterDtoFactory;
use App\Models\Repositories\UpdateOpenChatRepositoryInterface;
use App\Services\OpenChat\Store\OpenChatImageStore;

class OpenChatDeleter implements OpenChatDeleterInterface
{
    function __construct(
        private OpenChatUpdaterDtoFactory $openChatUpdaterDtoFactory,
        private UpdateOpenChatRepositoryInterface $updateRepository,
        private OpenChatImageStore $openChatImageStore,
    ) {
    }

    function deleteOpenChat(int $open_chat_id, string $imgUrl): void
    {
        $updaterDto = $this->openChatUpdaterDtoFactory->mapToDeleteOpenChatDto($open_chat_id);
        $this->updateRepository->updateOpenChatRecord($updaterDto);
        $this->openChatImageStore->deleteImage($open_chat_id, $imgUrl);
    }
}

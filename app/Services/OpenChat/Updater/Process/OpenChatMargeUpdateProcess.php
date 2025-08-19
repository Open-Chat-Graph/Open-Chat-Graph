<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Updater\Process;

use App\Services\OpenChat\Dto\OpenChatUpdaterDtoFactory;
use App\Models\Repositories\UpdateOpenChatRepositoryInterface;
use App\Services\OpenChat\Dto\OpenChatDto;
use App\Services\OpenChat\Dto\OpenChatRepositoryDto;
use App\Services\OpenChat\Dto\OpenChatUpdaterDto;
use App\Services\OpenChat\Updater\OpenChatDeleterInterface;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;

class OpenChatMargeUpdateProcess
{
    function __construct(
        private OpenChatUpdaterDtoFactory $openChatUpdaterDtoFactory,
        private UpdateOpenChatRepositoryInterface $updateRepository,
        private OpenChatDeleterInterface $openChatDeleter,
    ) {
    }

    function mergeUpdateOpenChat(OpenChatRepositoryDto $repoDto, OpenChatDto|false $ocDto, bool $updateMember = true): OpenChatUpdaterDto|false
    {
        if ($ocDto === false || OpenChatServicesUtility::containsHashtagNolog($ocDto)) {
            $this->openChatDeleter->deleteOpenChat($repoDto);
            return false;
        }

        $updaterDto = $this->openChatUpdaterDtoFactory->mapToDto($repoDto, $ocDto, $updateMember);
        $this->updateRepository->updateOpenChatRecord($updaterDto);

        return $updaterDto;
    }
}

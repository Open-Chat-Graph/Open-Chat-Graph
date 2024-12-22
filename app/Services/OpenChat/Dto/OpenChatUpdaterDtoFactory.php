<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Dto;

use App\Services\OpenChat\Utility\OpenChatServicesUtility;

class OpenChatUpdaterDtoFactory
{
    private string $dateTime;

    function __construct()
    {
        $this->dateTime = OpenChatServicesUtility::getModifiedCronTime('now')->format('Y-m-d H:i:s');
    }

    function mapToDto(OpenChatRepositoryDto $repoDto, OpenChatDto $apiDto, bool $updateMember): OpenChatUpdaterDto
    {
        $updaterDto = new OpenChatUpdaterDto($repoDto->open_chat_id);

        foreach ($repoDto as $prop => $value) {
            if ($prop === 'open_chat_id') {
                continue;
            }

            if ($prop === 'memberCount' && !$updateMember) {
                $updaterDto->memberCount = null;
                continue;
            }

            if (!isset($apiDto->$prop) || $apiDto->$prop === $value) {
                $updaterDto->$prop = null;
                continue;
            }

            $updaterDto->$prop = $apiDto->$prop;
        }

        if (
            $updaterDto->name !== null
            || $updaterDto->desc !== null
            || $updaterDto->profileImageObsHash !== null
            || $updaterDto->joinMethodType !== null
            || $updaterDto->category !== null
            || $updaterDto->emblem !== null
            //|| $updaterDto->createdAt !== null
        ) {
            $updaterDto->rewriteUpdateAtTime($this->dateTime);
        }

        return $updaterDto;
    }

    function mapToDeleteOpenChatDto(int $open_chat_id): OpenChatUpdaterDto
    {
        $updaterDto = new OpenChatUpdaterDto($open_chat_id);

        $updaterDto->delete_flag = true;

        return $updaterDto;
    }
}

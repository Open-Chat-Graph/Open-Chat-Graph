<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Services\OpenChat\Dto\OpenChatRepositoryDto;
use App\Services\OpenChat\Dto\OpenChatUpdaterDto;

interface UpdateOpenChatRepositoryInterface
{
    /**
     * @return OpenChatRepositoryDto|false
     */
    public function getOpenChatDataById(int $id): OpenChatRepositoryDto|false;

    public function updateOpenChatRecord(OpenChatUpdaterDto $dto): void;

    public function getOpenChatIdByEmid(string $emid): int|false;

    /**
     * @param array{ open_chat_id: int, member: int } $oc
     * @param ?string Y-m-d H:i:s
     */
    public function updateMemberColumn(array $oc): void;

    public function updateUrl(int $open_chat_id, string $url): void;

    /**
     * @return array{ id:int, emid:string }[]
     */
    public function getEmptyUrlOpenChatId(): array;
}

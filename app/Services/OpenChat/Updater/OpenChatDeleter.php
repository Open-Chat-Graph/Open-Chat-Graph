<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Updater;

use App\Models\CommentRepositories\DeleteCommentRepositoryInterface;
use App\Models\Repositories\DeleteOpenChatRepositoryInterface;
use App\Services\Comment\CommentImageServiceInterface;
use App\Services\OpenChat\Dto\OpenChatRepositoryDto;

class OpenChatDeleter implements OpenChatDeleterInterface
{
    function __construct(
        private DeleteOpenChatRepositoryInterface $deleteOpenChatRepository,
        private DeleteCommentRepositoryInterface $deleteCommentRepository,
        private CommentImageServiceInterface $commentImageService,
    ) {
    }

    /** 管理画面等からIDのみで削除する場合 */
    function deleteOpenChatById(int $open_chat_id): bool
    {
        $filenames = $this->deleteCommentRepository->getCommentImageFilenames($open_chat_id);

        $result = $this->deleteOpenChatRepository->deleteOpenChat($open_chat_id);

        if (!empty($filenames)) {
            $this->commentImageService->deleteImages($filenames);
        }

        return $result;
    }

    function deleteOpenChat(OpenChatRepositoryDto $repoDto): void
    {
        $this->deleteOpenChatById($repoDto->open_chat_id);
    }
}

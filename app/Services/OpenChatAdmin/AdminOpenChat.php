<?php

declare(strict_types=1);

namespace App\Services\OpenChatAdmin;

use App\Models\CommentRepositories\CommentListRepositoryInterface;
use App\Models\CommentRepositories\CommentPostRepositoryInterface;
use App\Models\RecommendRepositories\RecommendRankingRepository;
use App\Services\OpenChatAdmin\Dto\AdminOpenChatDto;
use App\Models\Repositories\DB;

class AdminOpenChat
{
    function __construct(
        private RecommendRankingRepository $recommendRankingRepository,
        private CommentListRepositoryInterface $commentListRepository,
        private CommentPostRepositoryInterface $commentPostRepository,
    ) {
    }

    function getDto(int $id): AdminOpenChatDto
    {
        $dto = new AdminOpenChatDto;
        $dto->id = $id;
        $dto->recommendTag = $this->recommendRankingRepository->getRecommendTag($id);
        $dto->modifyTag = DB::fetchColumn("SELECT tag FROM modify_recommend WHERE id = {$id}");
        $dto->commentIdArray = $this->commentListRepository->getCommentIdArrayByOpenChatId($id);

        $banCreatedAt = $this->commentPostRepository->getBanRoomExpiry($id);
        if ($banCreatedAt !== false) {
            $expiry = (new \DateTime($banCreatedAt))->modify('+7 days');
            $now = new \DateTime();
            $diff = $now->diff($expiry);
            $dto->commentBanRemainingDays = $diff->invert ? 0 : $diff->days + ($diff->h > 0 || $diff->i > 0 ? 1 : 0);
        }

        return $dto;
    }
}

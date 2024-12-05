<?php

declare(strict_types=1);

namespace App\Services\OpenChatAdmin;

use App\Models\CommentRepositories\CommentListRepositoryInterface;
use App\Models\RecommendRepositories\RecommendRankingRepository;
use App\Services\OpenChatAdmin\Dto\AdminOpenChatDto;
use App\Models\Repositories\DB;

class AdminOpenChat
{
    function __construct(
        private RecommendRankingRepository $recommendRankingRepository,
        private CommentListRepositoryInterface $commentListRepository,
    ) {
    }

    function getDto(int $id): AdminOpenChatDto
    {
        $dto = new AdminOpenChatDto;
        $dto->id = $id;
        $dto->recommendTag = $this->recommendRankingRepository->getRecommendTag($id);
        $dto->modifyTag = DB::fetchColumn("SELECT tag FROM modify_recommend WHERE id = {$id}");;
        $dto->commentIdArray = $this->commentListRepository->getCommentIdArrayByOpenChatId($id);

        return $dto;
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Models\CommentRepositories\DeleteCommentRepositoryInterface;
use App\Models\Repositories\DB;
use App\Models\Repositories\RankingPosition\RankingPositionRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;

class DeleteOpenChatRepository implements DeleteOpenChatRepositoryInterface
{
    public function __construct(
        private StatisticsRepositoryInterface $statisticsRepository,
        private RankingPositionRepositoryInterface $rankingPositionRepository,
        private DeleteCommentRepositoryInterface $deleteCommentRepository,
    ) {}

    public function deleteOpenChat(int $open_chat_id): bool
    {
        $result = DB::executeAndCheckResult(
            "DELETE FROM
                open_chat
            WHERE
                id = :open_chat_id",
            compact('open_chat_id')
        );

        $this->statisticsRepository->deleteDailyStatistics($open_chat_id);
        $this->rankingPositionRepository->deleteDailyPosition($open_chat_id);

        $this->deleteCommentRepository->deleteCommentsAll($open_chat_id);

        return $result;
    }

    public function insertDeletedOpenChat(int $open_chat_id, string $emid): void
    {
        DB::executeAndCheckResult(
            "INSERT IGNORE INTO
                open_chat_deleted (id, emid)
            VALUES
                (:open_chat_id, :emid)",
            compact('open_chat_id', 'emid')
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\AppConfig;
use App\Models\CommentRepositories\RecentCommentListRepositoryInterface;
use App\Services\Auth\AuthInterface;
use App\Services\Storage\FileStorageInterface;

class RecentCommentApiController
{
    function __construct(
        private RecentCommentListRepositoryInterface $recentCommentListRepository,
        private FileStorageInterface $fileStorage,
    ) {}

    function index(int $open_chat_id)
    {
        if (!$open_chat_id) {
            $updatedAt = $this->fileStorage->getContents('@commentUpdatedAtMicrotime');
            handleRequestWithETagAndCache(
                "recent-comment-api{$updatedAt}",
                hourly: false
            );
        } else {
            noStore();
        }

        return $this->response('', $open_chat_id);
    }

    function nocache(AuthInterface $auth, int $open_chat_id)
    {
        $user_id = $auth->verifyCookieUserId();

        return $this->response($user_id, $open_chat_id);
    }

    private function response(string $user_id, int $open_chat_id)
    {
        $recentCommentList = $this->recentCommentListRepository->findRecentCommentOpenChatAll(
            0,
            15,
            '',
            $user_id,
            $open_chat_id
        );

        return view('components/open_chat_list_ranking_comment2', ['openChatList' => $recentCommentList]);
    }
}

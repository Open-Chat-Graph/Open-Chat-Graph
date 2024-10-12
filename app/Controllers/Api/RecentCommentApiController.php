<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\CommentRepositories\RecentCommentListRepositoryInterface;
use App\Services\Auth\AuthInterface;

class RecentCommentApiController
{
    function index(
        RecentCommentListRepositoryInterface $recentCommentListRepository,
        AuthInterface $auth,
    ) {
        try {
            $user_id = $auth->verifyCookieUserId();
        } catch (\Shared\Exceptions\UnauthorizedException $e) {
            $user_id = '';
        }

        $recentCommentList = $recentCommentListRepository->findRecentCommentOpenChatAll(
            0,
            15,
            '',
            $user_id
        );

        return view('components/open_chat_list_ranking_comment', ['openChatList' => $recentCommentList]);
    }
}

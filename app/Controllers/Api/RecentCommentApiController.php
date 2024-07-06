<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\AdminConfig;
use App\Models\CommentRepositories\RecentCommentListRepositoryInterface;
use App\Services\Auth\AuthInterface;

class RecentCommentApiController
{
    function index(
        RecentCommentListRepositoryInterface $recentCommentListRepository,
        AuthInterface $auth,
    ) {
        $user_id = $auth->verifyCookieUserId();

        $recentCommentList = $recentCommentListRepository->findRecentCommentOpenChatAll(
            0,
            15,
            AdminConfig::ADMIN_API_KEY,
            $user_id
        );

        return view('components/open_chat_list_ranking_comment', ['openChatList' => $recentCommentList]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Models\CommentRepositories\CommentPostRepositoryInterface;

class AdminBanUserController
{
    private const PER_PAGE = 50;

    function index(
        int $page,
        CommentPostRepositoryInterface $commentPostRepository
    ) {
        $totalCount = $commentPostRepository->getBanUserCount();
        $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * self::PER_PAGE;

        $banUsers = $commentPostRepository->getBanUsers(self::PER_PAGE, $offset);

        return view('admin/ban_users', [
            'banUsers' => $banUsers,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
        ]);
    }
}

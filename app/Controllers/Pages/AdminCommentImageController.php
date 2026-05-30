<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Models\CommentRepositories\CommentImageRepositoryInterface;
use App\Models\Repositories\OpenChatPageRepositoryInterface;

class AdminCommentImageController
{
    /**
     * コメント画像管理ページ
     * @param string $tab deleted|active
     * @param int $page ページ番号（1始まり）
     */
    function commentImages(
        CommentImageRepositoryInterface $commentImageRepository,
        OpenChatPageRepositoryInterface $openChatPageRepository,
        string $tab,
        int $page
    ) {
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $stats = $commentImageRepository->getImageStats();

        if ($tab === 'deleted') {
            $images = $commentImageRepository->getDeletedCommentImages($perPage, $offset);
            $totalCount = $commentImageRepository->getDeletedCommentImageCount();
        } else {
            $images = $commentImageRepository->getActiveCommentImages($perPage, $offset);
            $totalCount = $commentImageRepository->getActiveCommentImageCount();
        }

        $totalPages = max(1, (int) ceil($totalCount / $perPage));

        // 掲載中タブ: OpenChatタイトルを取得
        $openChatNames = [];
        if ($tab === 'active' && !empty($images)) {
            $openChatIds = array_unique(array_column($images, 'open_chat_id'));
            $openChatNames = $openChatPageRepository->getOpenChatNamesByIds($openChatIds);
        }

        return view('admin_comment_images_content', [
            'stats' => $stats,
            'images' => $images,
            'tab' => $tab,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'openChatNames' => $openChatNames,
        ]);
    }
}

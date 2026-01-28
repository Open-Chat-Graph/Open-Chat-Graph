<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\SecretsConfig;
use App\Models\CommentRepositories\CommentPostRepositoryInterface;
use App\Models\CommentRepositories\DeleteCommentRepositoryInterface;
use App\Services\Admin\AdminAuthService;
use App\Services\Admin\AdminTool;
use App\Services\OpenChatAdmin\AdminEndPoint;
use ExceptionHandler\ExceptionHandler;
use Shared\Exceptions\NotFoundException;

class AdminEndPointController
{
    function __construct(AdminAuthService $adminAuthService)
    {
        if (!$adminAuthService->auth()) {
            throw new NotFoundException;
        }
    }

    function index(string $type, string $id, AdminEndPoint $adminEndPoint)
    {
        $adminEndPoint->{$type}($id);

        try {
            purgeCacheCloudFlare(
                files: [
                    url("oc/{$id}"),
                    url("oc/{$id}?limit=hour"),
                    url("oc/{$id}?limit=month"),
                    url("oc/{$id}?limit=all"),
                ]
            );
        } catch (\RuntimeException $e) {
            AdminTool::sendDiscordNotify($e->getMessage());
            ExceptionHandler::errorLog($e);
        }

        return redirect("oc/{$id}/admin");
    }

    function deletecomment(int $commentId, int $id, int $flag, DeleteCommentRepositoryInterface $deleteCommentRepository)
    {
        $result = $deleteCommentRepository->deleteCommentByOcId($id, $commentId, $flag !== 3 ? $flag : null);
        if (!$result) {
            return view('admin/admin_message_page', ['title' => 'コメント削除', 'message' => '削除されたコメントはありません']);
        }

        if ($flag > 0) $deleteCommentRepository->deleteLikeByUserIdAndIp($id, $result['user_id'], $result['ip']);

        try {
            purgeCacheCloudFlare(
                files: [
                    url('recent-comment-api'),
                    url('comments-timeline'),
                ]
            );
        } catch (\RuntimeException $e) {
            AdminTool::sendDiscordNotify($e->getMessage());
            ExceptionHandler::errorLog($e);
        }

        return redirect("oc/{$id}/admin");
    }

    function deleteuser(
        int $commentId,
        int $id,
        CommentPostRepositoryInterface $commentPostRepo,
        DeleteCommentRepositoryInterface $deleteCommentRepository
    ) {
        $comment_id = $deleteCommentRepository->getCommentId($id, $commentId);
        if (!$comment_id) {
            return view('admin/admin_message_page', ['title' => 'ユーザー削除', 'message' => 'ユーザーがいません']);
        }

        $result = $commentPostRepo->addBanUser($comment_id);
        if (!$result) {
            return view('admin/admin_message_page', ['title' => 'ユーザー削除', 'message' => '削除されたユーザーはいません']);
        }

        $deleteCommentRepository->deleteCommentByUserIdAndIpAll($result['user_id'], $result['ip']);

        try {
            purgeCacheCloudFlare(
                files: [
                    url('recent-comment-api'),
                    url('comments-timeline'),
                ]
            );
        } catch (\RuntimeException $e) {
            AdminTool::sendDiscordNotify($e->getMessage());
            ExceptionHandler::errorLog($e);
        }

        return redirect("oc/{$id}/admin");
    }

    function commentbanroom(int $id, CommentPostRepositoryInterface $commentPostRepo)
    {
        $result = $commentPostRepo->addBanRoom($id);
        if (!$result) {
            return view('admin/admin_message_page', ['title' => '存在しない部屋です', 'message' => '存在しない部屋です']);
        }

        return redirect("oc/{$id}/admin");
    }
}

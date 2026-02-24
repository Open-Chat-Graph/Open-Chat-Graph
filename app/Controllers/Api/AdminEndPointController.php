<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\CommentRepositories\CommentImageRepositoryInterface;
use App\Models\CommentRepositories\CommentLogRepositoryInterface;
use App\Models\CommentRepositories\CommentPostRepositoryInterface;
use App\Models\CommentRepositories\DeleteCommentRepositoryInterface;
use App\Models\CommentRepositories\Enum\CommentLogType;
use App\Services\Admin\AdminAuthService;
use App\Services\Admin\AdminTool;
use App\Services\Comment\CommentImageService;
use App\Services\Comment\CommentImageServiceInterface;
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

    function deletecomment(
        int $commentId,
        int $id,
        int $flag,
        DeleteCommentRepositoryInterface $deleteCommentRepository,
        CommentImageRepositoryInterface $commentImageRepository,
        CommentImageServiceInterface $commentImageService,
        CommentLogRepositoryInterface $commentLogRepository
    ) {
        // ログ用にcomment_idを事前取得
        $comment_id = $deleteCommentRepository->getCommentId($id, $commentId);

        // 物理削除の場合、コメントが消える前に画像も削除
        if ($flag === 3 && $comment_id) {
            $filenames = $commentImageRepository->deleteByCommentId($comment_id);
            if (!empty($filenames)) {
                $commentImageService->deleteImages($filenames);
            }
        }

        $result = $deleteCommentRepository->deleteCommentByOcId($id, $commentId, $flag !== 3 ? $flag : null);
        if (!$result) {
            return view('admin/admin_message_page', ['title' => 'コメント削除', 'message' => '削除されたコメントはありません']);
        }

        // 管理者操作ログ記録
        if ($comment_id) {
            $type = $flag === 0 ? CommentLogType::AdminRestore : CommentLogType::AdminDelete;
            $commentLogRepository->addAdminLogs([$comment_id], $type);
        }

        if ($flag > 0 && $flag !== 4) $deleteCommentRepository->deleteLikeByUserIdAndIp($id, $result['user_id'], $result['ip']);

        // flag=2,4: 画像をpublic外に移動、flag=0: 画像をpublicに復元
        if (in_array($flag, [0, 2, 4, 5], true)) {
            $comment_id = $deleteCommentRepository->getCommentId($id, $commentId);
            if ($comment_id) {
                $images = $commentImageRepository->getImagesByCommentId($comment_id);
                $filenames = array_column($images, 'filename');
                if (!empty($filenames)) {
                    $flag === 0
                        ? $commentImageService->restoreImages($filenames)
                        : $commentImageService->hideImages($filenames);
                }
            }
        }

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
        DeleteCommentRepositoryInterface $deleteCommentRepository,
        CommentLogRepositoryInterface $commentLogRepository
    ) {
        $comment_id = $deleteCommentRepository->getCommentId($id, $commentId);
        if (!$comment_id) {
            return view('admin/admin_message_page', ['title' => 'ユーザー削除', 'message' => 'ユーザーがいません']);
        }

        $result = $commentPostRepo->addBanUser($comment_id);
        if (!$result) {
            return view('admin/admin_message_page', ['title' => 'ユーザー削除', 'message' => '削除されたユーザーはいません']);
        }

        // 管理者操作ログ記録
        $commentLogRepository->addAdminLogs([$comment_id], CommentLogType::AdminBanUser);

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

    function deletecommentsall(
        int $id,
        DeleteCommentRepositoryInterface $deleteCommentRepository,
        CommentImageRepositoryInterface $commentImageRepository,
        CommentImageServiceInterface $commentImageService,
        CommentLogRepositoryInterface $commentLogRepository
    ) {
        // ログ用に影響するcomment_idを事前取得（flag=1,2,4は除外 = softDeleteAllCommentsと同じ条件）
        $affectedIds = $deleteCommentRepository->getCommentIdsByOpenChatId($id, [1, 2, 4]);

        $filenames = $deleteCommentRepository->getCommentImageFilenames($id);
        $count = $deleteCommentRepository->softDeleteAllComments($id);

        // 管理者操作ログ記録
        if (!empty($affectedIds)) {
            $commentLogRepository->addAdminLogs($affectedIds, CommentLogType::AdminBulkDelete);
        }

        if (!empty($filenames)) {
            $commentImageService->hideImages($filenames);
        }

        try {
            purgeCacheCloudFlare(files: [
                url('recent-comment-api'),
                url('comments-timeline'),
            ]);
        } catch (\RuntimeException $e) {
            AdminTool::sendDiscordNotify($e->getMessage());
            ExceptionHandler::errorLog($e);
        }

        return redirect("oc/{$id}/admin");
    }

    function restorecommentsall(
        int $id,
        DeleteCommentRepositoryInterface $deleteCommentRepository,
        CommentImageRepositoryInterface $commentImageRepository,
        CommentImageServiceInterface $commentImageService,
        CommentLogRepositoryInterface $commentLogRepository
    ) {
        // ログ用にflag=5のcomment_idを事前取得
        $affectedIds = $deleteCommentRepository->getSoftDeletedCommentIds($id);

        // flag=5のコメントに紐づく画像を復元
        $filenames = $deleteCommentRepository->getSoftDeletedCommentImageFilenames($id);
        $count = $deleteCommentRepository->restoreSoftDeletedComments($id);

        // 管理者操作ログ記録
        if (!empty($affectedIds)) {
            $commentLogRepository->addAdminLogs($affectedIds, CommentLogType::AdminBulkRestore);
        }

        if (!empty($filenames)) {
            $commentImageService->restoreImages($filenames);
        }

        try {
            purgeCacheCloudFlare(files: [
                url('recent-comment-api'),
                url('comments-timeline'),
            ]);
        } catch (\RuntimeException $e) {
            AdminTool::sendDiscordNotify($e->getMessage());
            ExceptionHandler::errorLog($e);
        }

        return redirect("oc/{$id}/admin");
    }

    function deleteCommentImage(
        int $imageId,
        CommentImageRepositoryInterface $commentImageRepository,
        CommentImageServiceInterface $commentImageService
    ) {
        $filename = $commentImageRepository->deleteImageById($imageId);
        if (!$filename) {
            return view('admin/admin_message_page', ['title' => '画像削除', 'message' => '画像が見つかりません']);
        }

        $commentImageService->deleteImages([$filename]);
        return view('admin/admin_message_page', ['title' => '画像削除', 'message' => "画像 {$filename} を削除しました"]);
    }

    function commentImageStorageSize(
        CommentImageRepositoryInterface $commentImageRepository,
        CommentImageServiceInterface $commentImageService
    ) {
        $storageSize = $commentImageService->calculateStorageSize(
            array_column($commentImageRepository->getDeletedCommentImages(999999), 'filename')
        );

        return response($storageSize);
    }

    function deleteDeletedCommentImages(
        CommentImageRepositoryInterface $commentImageRepository,
        CommentImageServiceInterface $commentImageService
    ) {
        $images = $commentImageRepository->getDeletedCommentImages(999999);
        if (empty($images)) {
            return view('admin/admin_message_page', ['title' => '画像一括削除', 'message' => '削除対象の画像はありません']);
        }

        $filenames = array_column($images, 'filename');
        $commentImageService->deleteImages($filenames);

        $ids = array_column($images, 'id');
        $commentImageRepository->deleteImagesByIds($ids);

        $count = count($filenames);
        return view('admin/admin_message_page', ['title' => '画像一括削除', 'message' => "{$count}件の画像を削除しました"]);
    }

    /**
     * 削除済み画像配信API（管理者専用）
     */
    function commentImage(string $filename)
    {
        // ファイル名のバリデーション（hex32文字 + .webp）
        if (!preg_match('/^[a-f0-9]+\.webp$/', $filename)) {
            return response('Invalid filename', 400);
        }

        // public側を探す
        $path = CommentImageService::getImagePath($filename);
        if (file_exists($path)) {
            header('Content-Type: image/webp');
            header('Cache-Control: private, max-age=86400');
            readfile($path);
            exit;
        }

        // hidden側を探す
        $path = CommentImageService::getHiddenImagePath($filename);
        if (file_exists($path)) {
            header('Content-Type: image/webp');
            header('Cache-Control: private, max-age=86400');
            readfile($path);
            exit;
        }

        return response('Image not found', 404);
    }
}

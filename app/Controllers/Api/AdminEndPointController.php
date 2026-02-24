<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\AppConfig;
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

    private function requireConfirmation(string $title, string $description, string $action, array $params, ?string $cancelUrl = null)
    {
        if (!empty($_REQUEST['confirmed'])) {
            return null;
        }

        return view('admin/admin_confirm_page', [
            'title' => $title,
            'description' => $description,
            'action' => url($action),
            'params' => $params,
            'cancelUrl' => $cancelUrl ?? '/',
        ]);
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
        $flagLabel = AppConfig::COMMENT_FLAG_LABELS[$flag] ?? "flag={$flag}";
        $confirm = $this->requireConfirmation(
            "コメントのフラグ変更確認",
            "コメント #{$commentId} を「{$flagLabel}」に変更します。",
            'admin-api/deletecomment',
            ['id' => $id, 'commentId' => $commentId, 'flag' => $flag],
            url("oc/{$id}/admin")
        );
        if ($confirm) return $confirm;

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
        $confirm = $this->requireConfirmation(
            "ユーザーシャドウバン確認",
            "コメント #{$commentId} の投稿者をシャドウバンします。\n該当ユーザーの全コメントがシャドウ削除されます。",
            'admin-api/deleteuser',
            ['id' => $id, 'commentId' => $commentId],
            url("oc/{$id}/admin")
        );
        if ($confirm) return $confirm;

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
        $confirm = $this->requireConfirmation(
            "コメント禁止確認",
            "このオープンチャットのコメントを1週間禁止します。",
            'admin-api/commentbanroom',
            ['id' => $id],
            url("oc/{$id}/admin")
        );
        if ($confirm) return $confirm;

        $result = $commentPostRepo->addBanRoom($id);
        if (!$result) {
            return view('admin/admin_message_page', ['title' => '存在しない部屋です', 'message' => '存在しない部屋です']);
        }

        return redirect("oc/{$id}/admin");
    }

    function commentunbanroom(int $id, CommentPostRepositoryInterface $commentPostRepo)
    {
        $confirm = $this->requireConfirmation(
            "コメント禁止解除確認",
            "このオープンチャットのコメント禁止を解除します。",
            'admin-api/commentunbanroom',
            ['id' => $id],
            url("oc/{$id}/admin")
        );
        if ($confirm) return $confirm;

        $commentPostRepo->removeBanRoom($id);

        return redirect("oc/{$id}/admin");
    }

    function deletecommentsall(
        int $id,
        DeleteCommentRepositoryInterface $deleteCommentRepository,
        CommentImageRepositoryInterface $commentImageRepository,
        CommentImageServiceInterface $commentImageService,
        CommentLogRepositoryInterface $commentLogRepository
    ) {
        $confirm = $this->requireConfirmation(
            "全コメント通常削除確認",
            "このオープンチャットの全コメントを通常削除（flag=5）します。",
            'admin-api/deletecommentsall',
            ['id' => $id],
            url("oc/{$id}/admin")
        );
        if ($confirm) return $confirm;

        // ログ用に影響するcomment_idを事前取得（flag=2,4,5は除外 = softDeleteAllCommentsと同じ条件）
        $affectedIds = $deleteCommentRepository->getCommentIdsByOpenChatId($id, [2, 4, 5]);

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
        $confirm = $this->requireConfirmation(
            "全コメント復元確認",
            "削除されたコメント（シャドウ削除・通常削除）を全て復元します。",
            'admin-api/restorecommentsall',
            ['id' => $id],
            url("oc/{$id}/admin")
        );
        if ($confirm) return $confirm;

        // ログ用にflag=1,5のcomment_idを事前取得
        $affectedIds = $deleteCommentRepository->getDeletedCommentIds($id);

        // flag=1,5のコメントに紐づく画像を復元
        $filenames = $deleteCommentRepository->getDeletedCommentImageFilenames($id);
        $count = $deleteCommentRepository->restoreDeletedComments($id);

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
        $confirm = $this->requireConfirmation(
            "画像削除確認",
            "画像ID: {$imageId} を完全削除します。",
            'admin-api/deletecommentimage',
            ['imageId' => $imageId],
        );
        if ($confirm) return $confirm;

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
        $confirm = $this->requireConfirmation(
            "削除済み画像一括削除確認",
            "削除済みコメントに紐づく画像ファイルを全て物理削除します。",
            'admin-api/deletedcommentimages',
            [],
        );
        if ($confirm) return $confirm;

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

    function harddeletecommentsall(
        int $id,
        DeleteCommentRepositoryInterface $deleteCommentRepository,
        CommentImageServiceInterface $commentImageService,
        CommentLogRepositoryInterface $commentLogRepository
    ) {
        $confirm = $this->requireConfirmation(
            "全コメント完全削除確認",
            "このオープンチャットの全コメント・画像を完全削除します。\nこの操作は元に戻せません。",
            'admin-api/harddeletecommentsall',
            ['id' => $id],
            url("oc/{$id}/admin")
        );
        if ($confirm) return $confirm;

        // ログ用に全comment_idを事前取得
        $affectedIds = $deleteCommentRepository->getCommentIdsByOpenChatId($id, []);

        $filenames = $deleteCommentRepository->deleteCommentsAll($id);

        // 管理者操作ログ記録
        if (!empty($affectedIds)) {
            $commentLogRepository->addAdminLogs($affectedIds, CommentLogType::AdminBulkDelete);
        }

        if (!empty($filenames)) {
            $commentImageService->deleteImages($filenames);
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

    function bulkshadowban(
        int $id,
        CommentPostRepositoryInterface $commentPostRepo,
        DeleteCommentRepositoryInterface $deleteCommentRepository,
        CommentImageRepositoryInterface $commentImageRepository,
        CommentImageServiceInterface $commentImageService,
        CommentLogRepositoryInterface $commentLogRepository
    ) {
        $confirm = $this->requireConfirmation(
            "一斉シャドウバン確認",
            "このオープンチャットの全投稿者をBANし、全コメントをシャドウ削除します。\n画像はhiddenに移動されます。",
            'admin-api/bulkshadowban',
            ['id' => $id],
            url("oc/{$id}/admin")
        );
        if ($confirm) return $confirm;

        // 全投稿者BAN
        $commentPostRepo->addBanUsersInRoom($id);

        // ログ用に影響するcomment_idを事前取得（flag=1,2,4は除外 = shadowDeleteAllCommentsと同じ条件）
        $affectedIds = $deleteCommentRepository->getCommentIdsByOpenChatId($id, [1, 2, 4]);

        // 全コメントシャドウ削除
        $deleteCommentRepository->shadowDeleteAllComments($id);

        // 管理者操作ログ記録
        if (!empty($affectedIds)) {
            $commentLogRepository->addAdminLogs($affectedIds, CommentLogType::AdminBulkBanUsers);
        }

        // 画像をhiddenに移動
        $filenames = $deleteCommentRepository->getCommentImageFilenames($id);
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

    function unbanuser(
        int $banId,
        CommentPostRepositoryInterface $commentPostRepo,
        DeleteCommentRepositoryInterface $deleteCommentRepository
    ) {
        $confirm = $this->requireConfirmation(
            "シャドウバン解除確認",
            "バンID: {$banId} のユーザーのシャドウバンを解除します。\nシャドウ削除されたコメント(flag=1)が復元されます。",
            'admin-api/unbanuser',
            ['banId' => $banId],
            url('admin/ban-users')
        );
        if ($confirm) return $confirm;

        $result = $commentPostRepo->removeBanUser($banId);
        if (!$result) {
            return view('admin/admin_message_page', ['title' => 'バン解除', 'message' => '該当するバンユーザーが見つかりません']);
        }

        $restored = $deleteCommentRepository->restoreCommentsByUserIdAndIp($result['user_id'], $result['ip']);

        try {
            purgeCacheCloudFlare(files: [
                url('recent-comment-api'),
                url('comments-timeline'),
            ]);
        } catch (\RuntimeException $e) {
            AdminTool::sendDiscordNotify($e->getMessage());
            ExceptionHandler::errorLog($e);
        }

        return redirect('admin/ban-users');
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

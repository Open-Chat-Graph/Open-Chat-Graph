<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Models\CommentRepositories\CommentImageRepositoryInterface;
use App\Models\CommentRepositories\CommentLogRepositoryInterface;
use App\Models\CommentRepositories\Enum\CommentLogType;
use App\Models\Repositories\OpenChatPageRepositoryInterface;

/**
 * 管理者操作ログ閲覧コントローラー
 */
class AdminCommentLogController
{
    private const PER_PAGE = 50;

    /**
     * 操作ログ一覧ページ
     */
    function index(
        int $page,
        CommentLogRepositoryInterface $commentLogRepository,
        OpenChatPageRepositoryInterface $openChatPageRepository
    ) {
        $totalCount = $commentLogRepository->getAdminLogCount();
        $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * self::PER_PAGE;

        $logs = $commentLogRepository->getAdminLogs(self::PER_PAGE, $offset);

        // OC名を一括取得
        $ocIds = array_unique(array_filter(array_column($logs, 'open_chat_id')));
        $ocNames = !empty($ocIds) ? $openChatPageRepository->getOpenChatNamesByIds($ocIds) : [];

        return view('admin/log_admin_action', [
            'logs' => $logs,
            'ocNames' => $ocNames,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'flagLabels' => AppConfig::COMMENT_FLAG_LABELS,
        ]);
    }

    /**
     * 操作ログ詳細ページ
     */
    function detail(
        int $id,
        CommentLogRepositoryInterface $commentLogRepository,
        CommentImageRepositoryInterface $commentImageRepository,
        OpenChatPageRepositoryInterface $openChatPageRepository
    ) {
        $log = $commentLogRepository->getAdminLogDetail($id);
        if (!$log) {
            return response('Log not found', 404);
        }

        // OC名を取得（メインDB）
        $ocId = $log['open_chat_id'] ?? null;
        $ocName = '';
        if ($ocId) {
            $oc = $openChatPageRepository->getOpenChatById((int) $ocId);
            $ocName = $oc ? $oc['name'] : '';
        }

        // コメント画像を取得
        $images = [];
        if ($log['entity_id']) {
            $images = $commentImageRepository->getImagesByCommentId($log['entity_id']);
        }

        // 投稿者のIP/UAを取得
        $posterLog = false;
        if ($log['entity_id']) {
            $posterLog = $commentLogRepository->findAddCommentLog($log['entity_id']);
        }

        return view('admin/log_admin_action_detail', [
            'log' => $log,
            'ocId' => $ocId,
            'ocName' => $ocName,
            'images' => $images,
            'posterLog' => $posterLog,
            'flagLabels' => AppConfig::COMMENT_FLAG_LABELS,
        ]);
    }
}

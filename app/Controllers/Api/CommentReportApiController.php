<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\CommentRepositories\CommentListRepositoryInterface;
use App\Models\CommentRepositories\CommentLogRepositoryInterface;
use App\Models\CommentRepositories\CommentPostRepositoryInterface;
use App\Models\CommentRepositories\Enum\CommentLogType;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Services\Admin\AdminTool;
use App\Services\Auth\AuthInterface;
use App\Services\Auth\GoogleReCaptcha;

class CommentReportApiController
{
    function index(
        CommentPostRepositoryInterface $commentPostRepository,
        CommentListRepositoryInterface $commentListRepository,
        CommentLogRepositoryInterface $commentLogRepository,
        AuthInterface $auth,
        GoogleReCaptcha $googleReCaptcha,
        OpenChatPageRepositoryInterface $ocRepo,
        string $token,
        int $comment_id
    ) {
        $score = $googleReCaptcha->validate($token, 0.5);
        $report_user_id = $auth->loginCookieUserId();

        if ($commentPostRepository->getBanUser($report_user_id, getIP())) {
            return response(['success' => false]);
        }

        $comment = $commentListRepository->findCommentById($comment_id);
        if (!$comment) {
            return false;
        }

        $existsReport = $commentLogRepository->findReportLog(
            $comment_id,
            CommentLogType::Report,
            json_encode(compact('report_user_id'))
        );

        if ($existsReport) {
            return response(['success' => false]);
        }

        $logId = $commentLogRepository->addLog(
            $comment_id,
            CommentLogType::Report,
            getIP(),
            getUA(),
            json_encode(compact('report_user_id'))
        );

        $comment['report_log_id'] = $logId;
        $scoreStr = (string)floor($score * 10) / 10;
        $comment['recaptcha'] = "score: {$scoreStr}";
        $comment['report_user_hash'] = base62Hash($report_user_id, 'fnv132');
        $comment['report_user_ua'] = getUA();
        $comment['report_user_ip'] = getIP();

        $id = $comment['id'];
        $ocId = $comment['open_chat_id'];
        $deleteUrl = url(
            "admin-api/deletecomment?openExternalBrowser=1&id={$ocId}&commentId={$id}&flag=2"
        );
        $roomUrl = url("oc/{$ocId}/admin?openExternalBrowser=1");

        AdminTool::sendDiscordNotify(
            "通報: " . json_encode($comment, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n削除: {$deleteUrl}\n部屋: {$roomUrl}"
        );

        return response(['success' => true]);
    }
}

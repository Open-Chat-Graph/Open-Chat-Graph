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

        $id = $comment['id'];
        $ocId = $comment['open_chat_id'];
        $commentText = mb_substr($comment['text'], 0, 100);
        $reporterHash = substr(hash('sha256', $report_user_id), 0, 7);
        $posterHash = substr(hash('sha256', $comment['user_id'] ?? ''), 0, 7);

        // 部屋情報を取得
        $oc = $ocRepo->getOpenChatById($ocId);
        $roomInfo = $oc
            ? "{$oc['name']} (ID: {$ocId}, 👥{$oc['member']}人, " . getCategoryName((int)($oc['category'] ?? 0)) . ")"
            : "ID: {$ocId}";

        // コメント投稿者のIP/UAをlogテーブルから取得
        $posterLog = $commentLogRepository->findAddCommentLog($comment['comment_id']);
        $posterIp = $posterLog ? $posterLog['ip'] : '不明';
        $posterUa = $posterLog ? $posterLog['ua'] : '不明';

        $reporterIp = getIP();
        $reporterUa = getUA();

        $deleteUrl = url(
            "admin-api/deletecomment?openExternalBrowser=1&id={$ocId}&commentId={$id}&flag=2"
        );
        $roomUrl = url("oc/{$ocId}/admin?openExternalBrowser=1");

        AdminTool::sendDiscordNotify(
            "📢 **コメント通報**\n"
            . "\n**ルーム**\n"
            . "- {$roomInfo}\n"
            . "\n**コメント #{$id}**\n"
            . "- {$commentText}\n"
            . "\n**投稿者**\n"
            . "- 名前: {$comment['name']}\n"
            . "- hash: {$posterHash}\n"
            . "- IP: {$posterIp}\n"
            . "- UA: {$posterUa}\n"
            . "\n**通報者**\n"
            . "- hash: {$reporterHash}\n"
            . "- IP: {$reporterIp}\n"
            . "- UA: {$reporterUa}\n"
            . "\n🔗 [削除する]({$deleteUrl}) | [ルーム管理]({$roomUrl})"
        );

        return response(['success' => true]);
    }
}

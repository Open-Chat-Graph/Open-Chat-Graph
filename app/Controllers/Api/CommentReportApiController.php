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
        $commentText = $comment['text'];
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
        $posterIpHash = substr(hash('sha256', $posterIp), 0, 7);
        $posterUaHash = substr(hash('sha256', $posterUa), 0, 7);

        // 投稿者の他の名前を取得
        $posterNames = $commentLogRepository->findRecentNamesByUserIdOrIp($comment['user_id'] ?? '', $posterIp);
        $posterNameStr = '**' . ($comment['name'] ?: '匿名') . '**';
        $otherPosterNames = array_filter($posterNames, fn($n) => $n !== $comment['name']);
        if (!empty($otherPosterNames)) {
            $posterNameStr .= ', ' . implode(', ', $otherPosterNames);
        }

        $reporterIp = getIP();
        $reporterUa = getUA();
        $reporterIpHash = substr(hash('sha256', $reporterIp), 0, 7);
        $reporterUaHash = substr(hash('sha256', $reporterUa), 0, 7);

        // 通報者の最近の書き込みから名前を取得
        $reporterNames = $commentLogRepository->findRecentNamesByUserIdOrIp($report_user_id, $reporterIp);
        $reporterNameStr = !empty($reporterNames) ? implode(', ', $reporterNames) : '';

        $deleteUrl = url(
            "admin-api/deletecomment?openExternalBrowser=1&id={$ocId}&commentId={$id}&flag=2"
        );
        $roomUrl = url("oc/{$ocId}/admin?openExternalBrowser=1");

        AdminTool::sendDiscordNotify(
            "📢 **コメント通報**\n"
            . "\n**ルーム**\n"
            . "{$roomInfo}\n"
            . "\n**コメント #{$id}**\n"
            . "{$commentText}\n"
            . "\n**投稿者**\n"
            . "- 名前: {$posterNameStr}\n"
            . "- hash: {$posterHash}\n"
            . "- IP-hash: {$posterIpHash}\n"
            . "  - IP: {$posterIp}\n"
            . "- UA-hash: {$posterUaHash}\n"
            . "  - UA: {$posterUa}\n"
            . "\n**通報者**\n"
            . ($reporterNameStr ? "- 名前: {$reporterNameStr}\n" : '')
            . "- hash: {$reporterHash}\n"
            . "- IP-hash: {$reporterIpHash}\n"
            . "  - IP: {$reporterIp}\n"
            . "- UA-hash: {$reporterUaHash}\n"
            . "  - UA: {$reporterUa}\n"
            . "\n🔗 [削除する]({$deleteUrl}) | [ルーム管理]({$roomUrl})"
        );

        return response(['success' => true]);
    }
}

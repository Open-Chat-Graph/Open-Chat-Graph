<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\CommentRepositories\CommentImageRepositoryInterface;
use App\Models\CommentRepositories\CommentListRepositoryInterface;
use App\Models\CommentRepositories\CommentLogRepositoryInterface;
use App\Models\CommentRepositories\CommentPostRepositoryInterface;
use App\Models\CommentRepositories\Enum\CommentLogType;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Services\Admin\AdminTool;
use App\Services\Auth\AuthInterface;
use App\Services\Auth\GoogleReCaptcha;

class CommentImageReportApiController
{
    function index(
        CommentImageRepositoryInterface $commentImageRepository,
        CommentPostRepositoryInterface $commentPostRepository,
        CommentLogRepositoryInterface $commentLogRepository,
        CommentListRepositoryInterface $commentListRepository,
        OpenChatPageRepositoryInterface $ocRepo,
        AuthInterface $auth,
        GoogleReCaptcha $googleReCaptcha,
        string $token,
        int $image_id
    ) {
        $googleReCaptcha->validate($token, 0.5);
        $report_user_id = $auth->loginCookieUserId();

        if ($commentPostRepository->getBanUser($report_user_id, getIP())) {
            return response(['success' => false]);
        }

        $existsReport = $commentLogRepository->findReportLog(
            $image_id,
            CommentLogType::ImageReport,
            json_encode(compact('report_user_id'))
        );

        if ($existsReport) {
            return response(['success' => false]);
        }

        $commentLogRepository->addLog(
            $image_id,
            CommentLogType::ImageReport,
            getIP(),
            getUA(),
            json_encode(compact('report_user_id'))
        );

        $reporterHash = substr(hash('sha256', $report_user_id), 0, 7);
        $reporterIp = getIP();
        $reporterUa = getUA();
        $reporterIpHash = substr(hash('sha256', $reporterIp), 0, 7);
        $reporterUaHash = substr(hash('sha256', $reporterUa), 0, 7);

        // 通報者の最近の書き込みから名前を取得
        $reporterNames = $commentLogRepository->findRecentNamesByUserIdOrIp($report_user_id, $reporterIp);
        $reporterNameStr = !empty($reporterNames) ? implode(', ', $reporterNames) : '';

        // 通報画像のURL
        $imageFilename = $commentImageRepository->getFilenameByImageId($image_id);
        $imageUrl = $imageFilename
            ? url('comment-img/' . substr($imageFilename, 0, 2) . '/' . $imageFilename)
            : '';

        // 画像IDからコメント情報・部屋情報を取得
        $commentId = $commentImageRepository->getCommentIdByImageId($image_id);
        $comment = $commentId !== false ? $commentListRepository->findCommentById($commentId) : false;

        if ($comment) {
            $ocId = $comment['open_chat_id'];
            $id = $comment['id'];
            $commentText = $comment['text'];
            $posterHash = substr(hash('sha256', $comment['user_id'] ?? ''), 0, 7);

            $oc = $ocRepo->getOpenChatById($ocId);
            $roomInfo = $oc
                ? "{$oc['name']} (ID: {$ocId}, 👥{$oc['member']}人, " . getCategoryName((int)($oc['category'] ?? 0)) . ")"
                : "ID: {$ocId}";

            $posterLog = $commentLogRepository->findAddCommentLog($commentId);
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

            $deleteImageUrl = url("admin-api/deletecomment?openExternalBrowser=1&id={$ocId}&commentId={$id}&flag=4");
            $deleteCommentUrl = url(
                "admin-api/deletecomment?openExternalBrowser=1&id={$ocId}&commentId={$id}&flag=2"
            );
            $roomUrl = url("oc/{$ocId}/admin?openExternalBrowser=1");

            AdminTool::sendDiscordNotify(
                "🖼️ **画像通報**\n"
                . "\n**ルーム**\n"
                . "{$roomInfo}\n"
                . "\n**コメント #{$id}**\n"
                . "{$commentText}\n"
                . "\n**投稿者**\n"
                . "- 名前: {$posterNameStr}\n"
                . "- ID: {$posterHash}\n"
                . "- IP-hash: {$posterIpHash}\n"
                . "  - IP: {$posterIp}\n"
                . "- UA-hash: {$posterUaHash}\n"
                . "  - UA: {$posterUa}\n"
                . "\n**通報者**\n"
                . ($reporterNameStr ? "- 名前: {$reporterNameStr}\n" : '')
                . "- ID: {$reporterHash}\n"
                . "- IP-hash: {$reporterIpHash}\n"
                . "  - IP: {$reporterIp}\n"
                . "- UA-hash: {$reporterUaHash}\n"
                . "  - UA: {$reporterUa}\n"
                . "\n🔗 [画像削除]({$deleteImageUrl}) | [コメント削除]({$deleteCommentUrl}) | [ルーム管理]({$roomUrl})"
                . ($imageUrl ? "\n{$imageUrl}" : '')
            );
        } else {
            $deleteImageUrl = url("admin-api/deletecommentimage?openExternalBrowser=1&imageId={$image_id}");
            AdminTool::sendDiscordNotify(
                "🖼️ **画像通報**\n"
                . "\n**画像ID**: {$image_id}\n"
                . "\n**通報者**\n"
                . ($reporterNameStr ? "- 名前: {$reporterNameStr}\n" : '')
                . "- ID: {$reporterHash}\n"
                . "- IP-hash: {$reporterIpHash}\n"
                . "  - IP: {$reporterIp}\n"
                . "- UA-hash: {$reporterUaHash}\n"
                . "  - UA: {$reporterUa}\n"
                . "\n🔗 [画像削除]({$deleteImageUrl})"
                . ($imageUrl ? "\n{$imageUrl}" : '')
            );
        }

        return response(['success' => true]);
    }
}

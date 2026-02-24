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

class CommentReportApiController
{
    function __construct(
        private CommentPostRepositoryInterface $commentPostRepository,
        private CommentListRepositoryInterface $commentListRepository,
        private CommentLogRepositoryInterface $commentLogRepository,
        private OpenChatPageRepositoryInterface $ocRepo,
        private AuthInterface $auth,
        private GoogleReCaptcha $googleReCaptcha,
    ) {}

    function reportComment(string $token, int $comment_id)
    {
        $reportUserId = $this->validateReporter($token);
        if ($reportUserId === false) return response(['success' => false]);

        $comment = $this->commentListRepository->findCommentById($comment_id);
        if (!$comment) return false;

        if ($this->isDuplicateReport($comment_id, CommentLogType::Report, $reportUserId)) {
            return response(['success' => false]);
        }

        $this->commentLogRepository->addLog(
            $comment_id, CommentLogType::Report, getIP(), getUA(),
            json_encode(['report_user_id' => $reportUserId])
        );

        $id = $comment['id'];
        $ocId = $comment['open_chat_id'];
        $reporter = $this->buildReporterInfo($reportUserId);
        $poster = $this->buildPosterInfo($comment);
        $roomInfo = $this->buildRoomInfo($ocId);

        $deleteUrl = url("admin-api/deletecomment?openExternalBrowser=1&id={$ocId}&commentId={$id}&flag=2");
        $roomUrl = url("oc/{$ocId}/admin?openExternalBrowser=1");
        $logUrl = url("admin/log/admin-action?openExternalBrowser=1");

        AdminTool::sendDiscordNotify(
            "📢 **コメント通報**\n"
            . "\n**ルーム**\n{$roomInfo}\n"
            . "\n**コメント #{$id}**\n{$comment['text']}\n"
            . $this->formatPosterSection($poster)
            . $this->formatReporterSection($reporter)
            . "\n> 🗑️ [削除する]({$deleteUrl})\n"
            . "> 🏠 [ルーム管理]({$roomUrl})\n"
            . "> 📋 [操作ログ]({$logUrl})"
        );

        return response(['success' => true]);
    }

    function reportImage(
        string $token,
        int $image_id,
        CommentImageRepositoryInterface $commentImageRepository,
    ) {
        $reportUserId = $this->validateReporter($token);
        if ($reportUserId === false) return response(['success' => false]);

        if ($this->isDuplicateReport($image_id, CommentLogType::ImageReport, $reportUserId)) {
            return response(['success' => false]);
        }

        $this->commentLogRepository->addLog(
            $image_id, CommentLogType::ImageReport, getIP(), getUA(),
            json_encode(['report_user_id' => $reportUserId])
        );

        $reporter = $this->buildReporterInfo($reportUserId);

        $imageFilename = $commentImageRepository->getFilenameByImageId($image_id);
        $imageUrl = $imageFilename
            ? url('comment-img/' . substr($imageFilename, 0, 2) . '/' . $imageFilename)
            : '';

        $commentId = $commentImageRepository->getCommentIdByImageId($image_id);
        $comment = $commentId !== false ? $this->commentListRepository->findCommentById($commentId) : false;

        if ($comment) {
            $id = $comment['id'];
            $ocId = $comment['open_chat_id'];
            $poster = $this->buildPosterInfo($comment);
            $roomInfo = $this->buildRoomInfo($ocId);

            $deleteImageUrl = url("admin-api/deletecomment?openExternalBrowser=1&id={$ocId}&commentId={$id}&flag=4");
            $deleteCommentUrl = url("admin-api/deletecomment?openExternalBrowser=1&id={$ocId}&commentId={$id}&flag=2");
            $roomUrl = url("oc/{$ocId}/admin?openExternalBrowser=1");
            $logUrl = url("admin/log/admin-action?openExternalBrowser=1");

            AdminTool::sendDiscordNotify(
                "🖼️ **画像通報**\n"
                . "\n**ルーム**\n{$roomInfo}\n"
                . "\n**コメント #{$id}**\n{$comment['text']}\n"
                . $this->formatPosterSection($poster)
                . $this->formatReporterSection($reporter)
                . "\n> 🖼️ [画像削除]({$deleteImageUrl})\n"
                . "> 🗑️ [コメント削除]({$deleteCommentUrl})\n"
                . "> 🏠 [ルーム管理]({$roomUrl})\n"
                . "> 📋 [操作ログ]({$logUrl})"
                . ($imageUrl ? "\n{$imageUrl}" : '')
            );
        } else {
            $deleteImageUrl = url("admin-api/deletecommentimage?openExternalBrowser=1&imageId={$image_id}");

            AdminTool::sendDiscordNotify(
                "🖼️ **画像通報**\n"
                . "\n**画像ID**: {$image_id}\n"
                . $this->formatReporterSection($reporter)
                . "\n> 🗑️ [画像削除]({$deleteImageUrl})"
                . ($imageUrl ? "\n{$imageUrl}" : '')
            );
        }

        return response(['success' => true]);
    }

    /**
     * reCAPTCHA検証 + BAN判定
     * @return string|false report_user_id or false
     */
    private function validateReporter(string $token): string|false
    {
        $this->googleReCaptcha->validate($token, 0.5);
        $reportUserId = $this->auth->loginCookieUserId();

        if ($this->commentPostRepository->getBanUser($reportUserId, getIP())) {
            return false;
        }

        return $reportUserId;
    }

    private function isDuplicateReport(int $entityId, CommentLogType $type, string $reportUserId): bool
    {
        return $this->commentLogRepository->findReportLog(
            $entityId, $type, json_encode(['report_user_id' => $reportUserId])
        );
    }

    /** @return array{ hash: string, ip: string, ua: string, ipHash: string, uaHash: string, nameStr: string } */
    private function buildReporterInfo(string $reportUserId): array
    {
        $ip = getIP();
        $ua = getUA();
        $names = $this->commentLogRepository->findRecentNamesByUserIdOrIp($reportUserId, $ip);

        return [
            'hash' => substr(hash('sha256', $reportUserId), 0, 7),
            'ip' => $ip,
            'ua' => $ua,
            'ipHash' => substr(hash('sha256', $ip), 0, 7),
            'uaHash' => substr(hash('sha256', $ua), 0, 7),
            'nameStr' => !empty($names) ? implode(', ', $names) : '',
        ];
    }

    /** @return array{ hash: string, ip: string, ua: string, ipHash: string, uaHash: string, nameStr: string } */
    private function buildPosterInfo(array $comment): array
    {
        $posterLog = $this->commentLogRepository->findAddCommentLog($comment['comment_id']);
        $ip = $posterLog ? $posterLog['ip'] : '不明';
        $ua = $posterLog ? $posterLog['ua'] : '不明';
        $userId = $comment['user_id'] ?? '';

        $names = $this->commentLogRepository->findRecentNamesByUserIdOrIp($userId, $ip);
        $nameStr = '**' . ($comment['name'] ?: '匿名') . '**';
        $otherNames = array_filter($names, fn($n) => $n !== $comment['name']);
        if (!empty($otherNames)) {
            $nameStr .= ', ' . implode(', ', $otherNames);
        }

        return [
            'hash' => substr(hash('sha256', $userId), 0, 7),
            'ip' => $ip,
            'ua' => $ua,
            'ipHash' => substr(hash('sha256', $ip), 0, 7),
            'uaHash' => substr(hash('sha256', $ua), 0, 7),
            'nameStr' => $nameStr,
        ];
    }

    private function buildRoomInfo(int $ocId): string
    {
        $oc = $this->ocRepo->getOpenChatById($ocId);
        return $oc
            ? "{$oc['name']} (ID: {$ocId}, 👥{$oc['member']}人, " . getCategoryName((int)($oc['category'] ?? 0)) . ")"
            : "ID: {$ocId}";
    }

    private function formatPosterSection(array $poster): string
    {
        return "\n**投稿者**\n"
            . "- 名前: {$poster['nameStr']}\n"
            . "- ID: {$poster['hash']}\n"
            . "- IP-hash: {$poster['ipHash']}\n"
            . "  - IP: {$poster['ip']}\n"
            . "- UA-hash: {$poster['uaHash']}\n"
            . "  - UA: {$poster['ua']}\n";
    }

    private function formatReporterSection(array $reporter): string
    {
        return "\n**通報者**\n"
            . ($reporter['nameStr'] ? "- 名前: {$reporter['nameStr']}\n" : '')
            . "- ID: {$reporter['hash']}\n"
            . "- IP-hash: {$reporter['ipHash']}\n"
            . "  - IP: {$reporter['ip']}\n"
            . "- UA-hash: {$reporter['uaHash']}\n"
            . "  - UA: {$reporter['ua']}\n";
    }
}

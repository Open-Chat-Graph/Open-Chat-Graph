<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\AppConfig;
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

        return $this->handleReport($comment_id, CommentLogType::Report, $reportUserId, $comment);
    }

    function reportImage(
        string $token,
        int $image_id,
        CommentImageRepositoryInterface $commentImageRepository,
    ) {
        $reportUserId = $this->validateReporter($token);
        if ($reportUserId === false) return response(['success' => false]);

        $commentId = $commentImageRepository->getCommentIdByImageId($image_id);
        if ($commentId === false) return false;

        $comment = $this->commentListRepository->findCommentById($commentId);
        if (!$comment) return false;

        $imageFilename = $commentImageRepository->getFilenameByImageId($image_id);
        $imageUrl = $imageFilename
            ? url('comment-img/' . substr($imageFilename, 0, 2) . '/' . $imageFilename)
            : '';

        return $this->handleReport($image_id, CommentLogType::ImageReport, $reportUserId, $comment, $imageUrl);
    }

    private function handleReport(
        int $entityId,
        CommentLogType $logType,
        string $reportUserId,
        array $comment,
        string $imageUrl = '',
    ) {
        if ($this->isDuplicateReport($entityId, $logType, $reportUserId)) {
            return response(['success' => false]);
        }

        $this->commentLogRepository->addLog(
            $entityId, $logType, getIP(), getUA(),
            json_encode(['report_user_id' => $reportUserId])
        );

        $id = $comment['id'];
        $ocId = $comment['open_chat_id'];
        $reporter = $this->buildUserInfo($reportUserId);
        $poster = $this->buildPosterInfo($comment);
        $roomInfo = $this->buildRoomInfo($ocId);

        $base = "admin-api/deletecomment?openExternalBrowser=1&id={$ocId}&commentId={$id}";
        $isImage = $logType === CommentLogType::ImageReport;

        $deleteImageLine = $isImage
            ? "> 🖼️ [画像のみ削除](" . url("{$base}&flag=4") . ")\n"
            : '';

        AdminTool::sendDiscordNotify(
            ($isImage ? "🖼️ **画像通報**" : "📢 **コメント通報**") . "\n"
            . "\n**ルーム**\n{$roomInfo}\n"
            . "\n**コメント #{$id}**\n{$comment['text']}\n"
            . $this->formatPosterSection($poster)
            . $this->formatReporterSection($reporter)
            . "\n{$deleteImageLine}"
            . "> 🔇 [シャドウ削除](" . url("{$base}&flag=1") . ")\n"
            . "> 🗑️ [通常削除](" . url("{$base}&flag=5") . ")\n"
            . "> ❌ [完全削除](" . url("{$base}&flag=3") . ")\n"
            . "> 🚫 [ユーザーシャドウバン](" . url("admin-api/deleteuser?openExternalBrowser=1&id={$ocId}&commentId={$id}") . ")\n"
            . "> 🏠 [ルーム管理](" . url("oc/{$ocId}/admin?openExternalBrowser=1") . ")\n"
            . "> 📋 [操作ログ](" . url("admin/log/admin-action?openExternalBrowser=1") . ")"
            . ($imageUrl ? "\n{$imageUrl}" : '')
        );

        return response(['success' => true]);
    }

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
        if (!AppConfig::$skipDuplicateReport) {
            return false;
        }

        return $this->commentLogRepository->findReportLog(
            $entityId, $type, json_encode(['report_user_id' => $reportUserId])
        );
    }

    private function buildUserInfo(string $userId): array
    {
        $ip = getIP();
        $ua = getUA();
        $names = $this->commentLogRepository->findRecentNamesByUserIdOrIp($userId, $ip);

        return [
            'hash' => substr(hash('sha256', $userId), 0, 7),
            'ipHash' => substr(hash('sha256', $ip), 0, 7),
            'uaHash' => substr(hash('sha256', $ua), 0, 7),
            'ip' => $ip,
            'ua' => $ua,
            'nameStr' => !empty($names) ? implode(', ', $names) : '',
        ];
    }

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
            'ipHash' => substr(hash('sha256', $ip), 0, 7),
            'uaHash' => substr(hash('sha256', $ua), 0, 7),
            'ip' => $ip,
            'ua' => $ua,
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

    private function formatPosterSection(array $info): string
    {
        return "\n**投稿者**\n"
            . "- 名前: {$info['nameStr']}\n"
            . "- ID: {$info['hash']}\n"
            . "- IP-hash: {$info['ipHash']}\n"
            . "  - IP: {$info['ip']}\n"
            . "- UA-hash: {$info['uaHash']}\n"
            . "  - UA: {$info['ua']}\n";
    }

    private function formatReporterSection(array $info): string
    {
        return "\n**通報者**\n"
            . ($info['nameStr'] ? "- 名前: {$info['nameStr']}\n" : '')
            . "- ID: {$info['hash']}\n"
            . "- IP-hash: {$info['ipHash']}\n"
            . "  - IP: {$info['ip']}\n"
            . "- UA-hash: {$info['uaHash']}\n"
            . "  - UA: {$info['ua']}\n";
    }
}

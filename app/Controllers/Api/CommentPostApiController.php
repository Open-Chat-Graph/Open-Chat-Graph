<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\SecretsConfig;
use App\Models\CommentRepositories\CommentLogRepositoryInterface;
use App\Models\CommentRepositories\CommentPostRepositoryInterface;
use App\Models\CommentRepositories\Dto\CommentPostApiArgs;
use App\Models\CommentRepositories\Enum\CommentLogType;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Services\Admin\AdminTool;
use App\Services\Auth\AuthInterface;
use App\Services\Auth\GoogleReCaptcha;
use App\Services\Storage\FileStorageInterface;
use ExceptionHandler\ExceptionHandler;

class CommentPostApiController
{
    function index(
        CommentPostRepositoryInterface $commentPostRepository,
        CommentLogRepositoryInterface $commentLogRepository,
        OpenChatPageRepositoryInterface $openChatPageRepository,
        AuthInterface $auth,
        GoogleReCaptcha $googleReCaptcha,
        FileStorageInterface $fileStorage,
        string $token,
        int $open_chat_id,
        string $name,
        string $text
    ) {
        $score = $googleReCaptcha->validate($token, 0.5);

        if (
            ($open_chat_id && !$openChatPageRepository->isExistsOpenChat($open_chat_id))
            || $commentPostRepository->getBanRoomWeek($open_chat_id)
        ) {
            return false;
        }

        $user_id = $auth->verifyCookieUserId();
        $flag = $commentPostRepository->getBanUser($user_id, getIP()) ? 1 : 0;
        $args = new CommentPostApiArgs(
            $user_id,
            $open_chat_id,
            $name,
            $text,
            $flag,
        );

        $commentId = $commentPostRepository->addComment($args);

        $commentLogRepository->addLog(
            $commentId,
            CommentLogType::AddComment,
            getIP(),
            getUA(),
            "{$score}"
        );

        if (!$flag) {
            try {
                purgeCacheCloudFlare(
                    files: [
                        url('recent-comment-api'),
                        url('comments-timeline')
                    ]
                );
            } catch (\RuntimeException $e) {
                AdminTool::sendDiscordNotify($e->getMessage());
                ExceptionHandler::errorLog($e);
            }

            $fileStorage->safeFileRewrite('@commentUpdatedAtMicrotime', (string)microtime(true));
        } else {
            cookie(['comment_flag' => (string)$flag]);
        }

        return response([
            'commentId' => $commentId,
            'userId' => $args->user_id === SecretsConfig::$adminApiKey ? '管理者' : base62Hash($args->user_id, 'fnv132'),
            'userIdHash' => substr(hash('sha256', $args->user_id), 0, 7),
            'uaHash' => substr(hash('sha256', getUA()), 0, 7),
            'ipHash' => substr(hash('sha256', getIP()), 0, 7),
        ]);
    }
}

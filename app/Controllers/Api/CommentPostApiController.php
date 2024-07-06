<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\AdminConfig;
use App\Models\CommentRepositories\CommentLogRepositoryInterface;
use App\Models\CommentRepositories\CommentPostRepositoryInterface;
use App\Models\CommentRepositories\Dto\CommentPostApiArgs;
use App\Models\CommentRepositories\Enum\CommentLogType;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Services\Auth\AuthInterface;
use App\Services\Auth\GoogleReCaptcha;

class CommentPostApiController
{
    function index(
        CommentPostRepositoryInterface $commentPostRepository,
        CommentLogRepositoryInterface $commentLogRepository,
        OpenChatPageRepositoryInterface $openChatPageRepository,
        AuthInterface $auth,
        GoogleReCaptcha $googleReCaptcha,
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
            purgeCacheCloudFlare(
                AdminConfig::CloudFlareZoneID,
                AdminConfig::CloudFlareApiKey,
                [url()]
            );
        } else {
            cookie(['comment_flag' => (string)$flag]);
        }

        return response([
            'commentId' => $commentId,
            'userId' => $args->user_id === AdminConfig::ADMIN_API_KEY ? '管理者' : base62Hash($args->user_id, 'fnv132')
        ]);
    }
}

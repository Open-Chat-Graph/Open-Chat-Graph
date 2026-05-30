<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\CommentRepositories\CommentImageRepositoryInterface;
use App\Models\CommentRepositories\CommentListRepositoryInterface;
use App\Models\CommentRepositories\CommentPostRepositoryInterface;
use App\Models\CommentRepositories\Dto\CommentListApi;
use App\Models\CommentRepositories\Dto\CommentListApiArgs;
use App\Services\Auth\AuthInterface;

class CommentListApiController
{
    function index(
        CommentListRepositoryInterface $commentListRepository,
        CommentPostRepositoryInterface $commentPostRepository,
        CommentImageRepositoryInterface $commentImageRepository,
        AuthInterface $auth,
        int $page,
        int $limit,
        int $open_chat_id
    ) {
        $args = new CommentListApiArgs(
            $page,
            $limit,
            $open_chat_id,
            $auth->loginCookieUserId()
        );

        $list = $commentListRepository->findComments($args);

        $flag = $commentPostRepository->getBanUser($args->user_id, getIP()) ? 1 : 0;
        if ($flag)
            cookie(['comment_flag' => (string)$flag], httpOnly: false);

        // 画像をバッチ取得
        $commentIds = array_map(fn(CommentListApi $el) => $el->commentId, $list);
        $imagesMap = $commentImageRepository->getImagesByCommentIds($commentIds);

        return response(array_map(function (CommentListApi $el) use ($imagesMap) {
            $data = $el->getResponseArray();
            $data['images'] = in_array($el->flag, [4, 5], true) ? [] : ($imagesMap[$el->commentId] ?? []);
            return $data;
        }, $list));
    }
}

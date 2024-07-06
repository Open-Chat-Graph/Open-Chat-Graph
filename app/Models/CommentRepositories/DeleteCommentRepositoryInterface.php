<?php

namespace App\Models\CommentRepositories;

interface DeleteCommentRepositoryInterface
{
    /** @return array{user_id:string,ip:string}|false */
    function deleteComment(int $comment_id, ?int $flag): array|false;
    /** @return array{user_id:string,ip:string}|false */
    function deleteCommentByOcId(int $open_chat_id, int $id, ?int $flag = null): array|false;
    function deleteCommentsAll(int $open_chat_id): void;
    function deleteLikeByUserIdAndIp(int $open_chat_id, string $user_id, string $ip): int;
    function deleteCommentByUserIdAndIpAll(string $user_id, string $ip): void;
    function getCommentId(int $open_chat_id, int $id): int|false;
}

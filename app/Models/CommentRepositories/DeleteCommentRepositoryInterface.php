<?php

namespace App\Models\CommentRepositories;

interface DeleteCommentRepositoryInterface
{
    /** @return array{user_id:string,ip:string}|false */
    function deleteComment(int $comment_id, ?int $flag): array|false;
    /** @return array{user_id:string,ip:string}|false */
    function deleteCommentByOcId(int $open_chat_id, int $id, ?int $flag = null): array|false;
    /** @return string[] image filenames associated with the open_chat_id */
    function getCommentImageFilenames(int $open_chat_id): array;
    /** @return string[] deleted image filenames */
    function deleteCommentsAll(int $open_chat_id): array;
    function softDeleteAllComments(int $open_chat_id): int;
    function restoreSoftDeletedComments(int $open_chat_id): int;
    /** @return string[] image filenames associated with flag=5 comments */
    function getSoftDeletedCommentImageFilenames(int $open_chat_id): array;
    function deleteLikeByUserIdAndIp(int $open_chat_id, string $user_id, string $ip): int;
    function deleteCommentByUserIdAndIpAll(string $user_id, string $ip): void;
    function getCommentId(int $open_chat_id, int $id): int|false;
}

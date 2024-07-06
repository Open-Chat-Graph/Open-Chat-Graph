<?php

namespace App\Models\CommentRepositories;

use App\Models\CommentRepositories\Dto\CommentPostApiArgs;

interface CommentPostRepositoryInterface
{
    function addComment(CommentPostApiArgs $args): int;
    function addBanRoom(int $open_chat_id): int;
    function getBanRoomWeek(int $open_chat_id): int|false;
    /** @return array{ user_id:string,ip:string }|false */
    function addBanUser(int $comment_id): array|false;
    function getBanUser(string $user_id, string $ip): string|false;
}

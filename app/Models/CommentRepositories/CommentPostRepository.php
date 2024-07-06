<?php

declare(strict_types=1);

namespace App\Models\CommentRepositories;

use App\Models\CommentRepositories\Dto\CommentPostApiArgs;

class CommentPostRepository implements CommentPostRepositoryInterface
{
    function addComment(CommentPostApiArgs $args): int
    {
        $query =
            "INSERT INTO
                comment (open_chat_id, id, user_id, name, text, flag)
            SELECT
                :open_chat_id,
                (
                    SELECT
                        IFNULL(MAX(id), 0) + 1
                    FROM
                        comment
                    WHERE
                        open_chat_id = :open_chat_id
                ),
                :user_id,
                :name,
                :text,
                :flag";

        return CommentDB::executeAndGetLastInsertId($query, [
            'open_chat_id' => $args->open_chat_id,
            'user_id' => $args->user_id,
            'name' => $args->name,
            'text' => $args->text,
            'flag' => $args->flag,
        ]);
    }

    function addBanRoom(int $open_chat_id): int
    {
        $query =
            "INSERT INTO
                ban_room (open_chat_id)
            VALUES
                (:open_chat_id)";

        return CommentDB::executeAndGetLastInsertId($query, compact('open_chat_id'));
    }

    function getBanRoomWeek(int $open_chat_id): int|false
    {
        $query =
            "SELECT 
                open_chat_id
            FROM 
                ban_room 
            WHERE 
                open_chat_id = :open_chat_id 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            LIMIT
                1";

        return CommentDB::fetchColumn($query, compact('open_chat_id'));
    }

    function addBanUser(int $comment_id): array|false
    {
        $query =
            "SELECT
                t1.user_id,
                t2.ip
            FROM
                comment AS t1
                JOIN log AS t2 ON t1.comment_id = t2.entity_id AND type = 'AddComment'
            WHERE
                t1.comment_id = :comment_id
            LIMIT
                1";

        $user = CommentDB::fetch($query, compact('comment_id'));
        if (!$user)
            return false;

        $query2 =
            "INSERT INTO
                ban_user (user_id, ip)
            VALUES
                (:user_id, :ip)";

        CommentDB::execute($query2, $user);

        return $user;
    }

    function getBanUser(string $user_id, string $ip): string|false
    {
        $query =
            "SELECT 
                user_id
            FROM 
                ban_user 
            WHERE 
                user_id = :user_id 
                OR ip = :ip
            LIMIT
                1";

        return CommentDB::fetchColumn($query, compact('user_id', 'ip'));
    }
}

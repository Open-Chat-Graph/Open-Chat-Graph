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

    function addBanUsersInRoom(int $open_chat_id): int
    {
        $query =
            "SELECT DISTINCT
                t1.user_id,
                t2.ip
            FROM
                comment AS t1
                JOIN log AS t2 ON t1.comment_id = t2.entity_id AND t2.type = 'AddComment'
            WHERE
                t1.open_chat_id = :open_chat_id
                AND t1.user_id != ''";

        $users = CommentDB::fetchAll($query, compact('open_chat_id'));
        $count = 0;

        foreach ($users as $user) {
            $existing = CommentDB::fetchColumn(
                "SELECT user_id FROM ban_user WHERE user_id = :user_id AND ip = :ip LIMIT 1",
                $user
            );
            if ($existing) continue;

            CommentDB::execute(
                "INSERT INTO ban_user (user_id, ip) VALUES (:user_id, :ip)",
                $user
            );
            $count++;
        }

        return $count;
    }

    function removeBanRoom(int $open_chat_id): bool
    {
        return CommentDB::executeAndCheckResult(
            "DELETE FROM ban_room WHERE open_chat_id = :open_chat_id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            compact('open_chat_id')
        );
    }

    function getBanRoomExpiry(int $open_chat_id): string|false
    {
        return CommentDB::fetchColumn(
            "SELECT created_at FROM ban_room
             WHERE open_chat_id = :open_chat_id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY created_at DESC LIMIT 1",
            compact('open_chat_id')
        );
    }

    function getBanUsers(int $limit, int $offset): array
    {
        $query =
            "SELECT
                b.id,
                b.user_id,
                b.ip,
                b.created_at,
                IFNULL(
                    (SELECT name FROM comment WHERE user_id = b.user_id ORDER BY comment_id DESC LIMIT 1),
                    ''
                ) AS name
            FROM
                ban_user AS b
            ORDER BY
                b.id DESC
            LIMIT
                :limit
            OFFSET
                :offset";

        return CommentDB::fetchAll($query, compact('limit', 'offset'));
    }

    function getBanUserCount(): int
    {
        return (int) CommentDB::fetchColumn("SELECT COUNT(*) FROM ban_user", []);
    }

    function removeBanUser(int $banId): array|false
    {
        $query =
            "SELECT
                user_id, ip
            FROM
                ban_user
            WHERE
                id = :banId
            LIMIT
                1";

        $user = CommentDB::fetch($query, compact('banId'));
        if (!$user) return false;

        CommentDB::execute("DELETE FROM ban_user WHERE id = :banId", compact('banId'));

        return $user;
    }
}

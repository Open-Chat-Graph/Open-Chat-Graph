<?php

declare(strict_types=1);

namespace App\Models\CommentRepositories\Api;

use App\Models\CommentRepositories\CommentPostRepositoryInterface;
use App\Models\CommentRepositories\Dto\CommentPostApiArgs;
use App\Models\SQLite\SQLiteOcgraphSqlapi;

/**
 * Repository for comment post operations from ocgraph_sqlapi SQLite database
 * Read-only repository - write operations throw exceptions
 */
class ApiCommentPostRepository implements CommentPostRepositoryInterface
{
    function addComment(CommentPostApiArgs $args): int
    {
        throw new \RuntimeException('Write operation not supported in API repository');
    }

    function addBanRoom(int $open_chat_id): int
    {
        throw new \RuntimeException('Write operation not supported in API repository');
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
                AND created_at >= datetime('now', '-7 days')
            LIMIT
                1";

        return SQLiteOcgraphSqlapi::fetchColumn($query, compact('open_chat_id'));
    }

    function addBanUser(int $comment_id): array|false
    {
        throw new \RuntimeException('Write operation not supported in API repository');
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

        return SQLiteOcgraphSqlapi::fetchColumn($query, compact('user_id', 'ip'));
    }

    function addBanUsersInRoom(int $open_chat_id): int
    {
        throw new \RuntimeException('Write operation not supported in API repository');
    }

    function removeBanRoom(int $open_chat_id): bool
    {
        throw new \RuntimeException('Write operation not supported in API repository');
    }

    function getBanRoomExpiry(int $open_chat_id): string|false
    {
        return SQLiteOcgraphSqlapi::fetchColumn(
            "SELECT created_at FROM ban_room
             WHERE open_chat_id = :open_chat_id AND created_at >= datetime('now', '-7 days')
             ORDER BY created_at DESC LIMIT 1",
            compact('open_chat_id')
        );
    }
}

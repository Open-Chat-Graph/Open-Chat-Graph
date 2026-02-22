<?php

declare(strict_types=1);

namespace App\Models\CommentRepositories;

use App\Models\CommentRepositories\Dto\CommentListApiArgs;
use App\Models\CommentRepositories\Dto\CommentListApi;

class CommentListRepository implements CommentListRepositoryInterface
{
    function findComments(CommentListApiArgs $args): array
    {
        $query =
        "SELECT
                c.id,
                c.comment_id AS commentId,
                CASE c.flag AND c.user_id != :user_id
                    WHEN 0 THEN c.name
                    ELSE 'Anonymous'
                END AS name,
                CASE c.flag AND c.user_id != :user_id
                    WHEN 0 THEN c.text
                    ELSE ''
                END AS text,
                c.time,
                c.user_id AS userId,
                CASE
                    WHEN c.user_id = :user_id THEN 0
                    ELSE c.flag
                END AS flag,
                IFNULL(l.empathy, 0) AS empathyCount,
                IFNULL(l.insights, 0) AS insightsCount,
                IFNULL(l.negative, 0) AS negativeCount,
                IFNULL(l.voted, '') AS voted,
                lg.ip AS logIp,
                lg.ua AS logUa
            FROM
                comment AS c
                LEFT JOIN log AS lg ON lg.entity_id = c.comment_id AND lg.type = 'AddComment'
                LEFT JOIN (
                    SELECT
                        comment_id,
                        COUNT(
                            CASE
                                WHEN type = 'empathy' THEN 1
                            END
                        ) AS empathy,
                        COUNT(
                            CASE
                                WHEN type = 'insights' THEN 1
                            END
                        ) AS insights,
                        COUNT(
                            CASE
                                WHEN type = 'negative' THEN 1
                            END
                        ) AS negative,
                        GROUP_CONCAT(
                            CASE
                                WHEN user_id = :user_id THEN type
                                ELSE NULL
                            END
                        ) AS voted
                    FROM
                        `like`
                    GROUP BY
                        comment_id
                ) AS l ON l.comment_id = c.comment_id
            WHERE
                c.open_chat_id = :open_chat_id
                AND (c.flag != 1 OR c.user_id = :user_id)
            ORDER BY
                c.comment_id DESC
            LIMIT
                :offset, :limit";

        return CommentDB::fetchAll($query, [
            'user_id' => $args->user_id,
            'open_chat_id' => $args->open_chat_id,
            'offset' => $args->page * $args->limit,
            'limit' => $args->limit,
        ], [\PDO::FETCH_CLASS, CommentListApi::class]);
    }

    function findCommentById(int $comment_id): array|false
    {
        $query =
            "SELECT
                open_chat_id,
                id,
                name,
                time,
                text,
                comment_id
            FROM
                comment
            WHERE
                comment_id = :comment_id";

        return CommentDB::fetch($query, compact('comment_id'));
    }

    function getCommentIdArrayByOpenChatId(int $open_chat_id): array
    {
        $query =
            "SELECT
                id
            FROM
                comment
            WHERE
                open_chat_id = :open_chat_id
            ORDER BY
                id DESC";

        return CommentDB::fetchAll($query, compact('open_chat_id'), [\PDO::FETCH_COLUMN, 0]);
    }

    function getCommentsAll(): array
    {
        $query =
            "SELECT
                *
            FROM
                comment";

        return CommentDB::fetchAll($query);
    }
}

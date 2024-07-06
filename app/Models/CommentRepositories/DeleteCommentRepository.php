<?php

declare(strict_types=1);

namespace App\Models\CommentRepositories;

class DeleteCommentRepository implements DeleteCommentRepositoryInterface
{
    function deleteComment(int $comment_id, ?int $flag): array|false
    {
        $id = compact('comment_id');
        $user_id = CommentDB::fetchColumn("SELECT user_id FROM comment WHERE comment_id = :comment_id", $id);
        if (!$user_id) return false;

        if (is_null($flag)) {
            $result = CommentDB::executeAndCheckResult("DELETE FROM comment WHERE comment_id = :comment_id", $id);
            CommentDB::execute("DELETE FROM `like` WHERE comment_id = :comment_id", $id);
        } else {
            $result = CommentDB::executeAndCheckResult("UPDATE comment SET flag = {$flag} WHERE comment_id = :comment_id", $id);
        }

        if (!$result) return false;

        $ip = CommentDB::fetchColumn(
            "SELECT
                ip
            FROM
                `log`
            WHERE
                `type` = 'AddComment'
                AND entity_id = :comment_id",
            $id
        ) ?: '';

        return compact('user_id', 'ip');
    }

    function getCommentId(int $open_chat_id, int $id): int|false
    {
        return CommentDB::fetchColumn(
            "SELECT comment_id FROM comment WHERE open_chat_id = :open_chat_id AND id = :id",
            compact('open_chat_id', 'id')
        );
    }

    function deleteCommentByOcId(int $open_chat_id, int $id, ?int $flag = null): array|false
    {
        $comment_id = $this->getCommentId($open_chat_id, $id);
        if (!$comment_id) return false;

        return $this->deleteComment($comment_id, $flag);
    }

    function deleteCommentsAll(int $open_chat_id): void
    {
        $id = compact('open_chat_id');

        CommentDB::execute(
            "DELETE FROM
                `like`
            WHERE
                comment_id IN (
                    SELECT
                        comment_id
                    FROM
                        comment
                    WHERE
                        open_chat_id = :open_chat_id
                )",
            $id
        );

        CommentDB::execute(
            "DELETE FROM comment WHERE open_chat_id = :open_chat_id",
            $id
        );
    }

    function deleteLikeByUserIdAndIp(int $open_chat_id, string $user_id, string $ip): int
    {
        return CommentDB::execute(
            "DELETE FROM
                `like`
            WHERE 
                id IN (
                    SELECT
                        t1.id
                    FROM
                        (SELECT * FROM `like`) AS t1
                        JOIN comment AS t2 ON t1.comment_id = t2.comment_id
                        AND t2.open_chat_id = :open_chat_id
                        JOIN `log` AS lt ON t1.id = lt.entity_id
                        AND lt.type = 'AddLike'
                    WHERE
                        t1.user_id = :user_id
                        OR lt.ip = :ip
                )",
            compact('open_chat_id', 'user_id', 'ip')
        )->rowCount();
    }

    function deleteCommentByUserIdAndIpAll(string $user_id, string $ip): void
    {
        CommentDB::execute(
            "DELETE FROM
                `like`
            WHERE 
                id IN (
                    SELECT
                        t1.id
                    FROM
                        (SELECT * FROM `like`) AS t1
                        JOIN comment AS t2 ON t1.comment_id = t2.comment_id
                        JOIN `log` AS lt ON t1.id = lt.entity_id
                        AND lt.type = 'AddLike'
                    WHERE
                        t1.user_id = :user_id
                        OR lt.ip = :ip
                )",
            compact('user_id', 'ip')
        );

        CommentDB::execute(
            "UPDATE
                comment
            SET
                flag = 1
            WHERE 
                comment_id IN (
                    SELECT
                        t1.comment_id
                    FROM
                        (SELECT * FROM comment) AS t1
                        JOIN `log` AS lt ON t1.comment_id = lt.entity_id
                        AND lt.type = 'AddComment'
                    WHERE
                        t1.user_id = :user_id
                        OR lt.ip = :ip
                )",
            compact('user_id', 'ip')
        );
    }
}

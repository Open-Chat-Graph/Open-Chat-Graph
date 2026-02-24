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

    /** @return string[] image filenames associated with the open_chat_id */
    function getCommentImageFilenames(int $open_chat_id): array
    {
        return array_column(
            CommentDB::fetchAll(
                "SELECT ci.filename FROM comment_image AS ci
                 JOIN comment AS c ON ci.comment_id = c.comment_id
                 WHERE c.open_chat_id = :open_chat_id",
                compact('open_chat_id')
            ),
            'filename'
        );
    }

    function deleteCommentsAll(int $open_chat_id): array
    {
        $id = compact('open_chat_id');

        // 画像ファイル名を取得（物理削除用）
        $filenames = array_column(
            CommentDB::fetchAll(
                "SELECT ci.filename FROM comment_image AS ci
                 JOIN comment AS c ON ci.comment_id = c.comment_id
                 WHERE c.open_chat_id = :open_chat_id",
                $id
            ),
            'filename'
        );

        // comment_image レコード削除
        CommentDB::execute(
            "DELETE FROM comment_image WHERE comment_id IN (
                SELECT comment_id FROM comment WHERE open_chat_id = :open_chat_id
            )",
            $id
        );

        // like 削除
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

        // comment 削除
        CommentDB::execute(
            "DELETE FROM comment WHERE open_chat_id = :open_chat_id",
            $id
        );

        return $filenames;
    }

    function softDeleteAllComments(int $open_chat_id): int
    {
        $id = compact('open_chat_id');

        // いいね削除
        CommentDB::execute(
            "DELETE FROM `like` WHERE comment_id IN (
                SELECT comment_id FROM comment WHERE open_chat_id = :open_chat_id
            )",
            $id
        );

        // 全コメントをflag=5に更新（flag=2通報,4画像削除は除外、flag=5は既に対象状態）
        return CommentDB::execute(
            "UPDATE comment SET flag = 5 WHERE open_chat_id = :open_chat_id AND flag NOT IN (2, 4, 5)",
            $id
        )->rowCount();
    }

    function restoreSoftDeletedComments(int $open_chat_id): int
    {
        return CommentDB::execute(
            "UPDATE comment SET flag = 0 WHERE open_chat_id = :open_chat_id AND flag = 5",
            compact('open_chat_id')
        )->rowCount();
    }

    function restoreDeletedComments(int $open_chat_id): int
    {
        return CommentDB::execute(
            "UPDATE comment SET flag = 0 WHERE open_chat_id = :open_chat_id AND flag IN (1, 2, 4, 5)",
            compact('open_chat_id')
        )->rowCount();
    }

    function getSoftDeletedCommentImageFilenames(int $open_chat_id): array
    {
        return array_column(
            CommentDB::fetchAll(
                "SELECT ci.filename FROM comment_image AS ci
                 JOIN comment AS c ON ci.comment_id = c.comment_id
                 WHERE c.open_chat_id = :open_chat_id AND c.flag = 5",
                compact('open_chat_id')
            ),
            'filename'
        );
    }

    function getDeletedCommentImageFilenames(int $open_chat_id): array
    {
        return array_column(
            CommentDB::fetchAll(
                "SELECT ci.filename FROM comment_image AS ci
                 JOIN comment AS c ON ci.comment_id = c.comment_id
                 WHERE c.open_chat_id = :open_chat_id AND c.flag IN (1, 2, 4, 5)",
                compact('open_chat_id')
            ),
            'filename'
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

    /** @return int[] */
    function getCommentIdsByOpenChatId(int $openChatId, array $excludeFlags): array
    {
        $params = ['openChatId' => $openChatId];

        if (empty($excludeFlags)) {
            $query = "SELECT comment_id FROM comment WHERE open_chat_id = :openChatId";
        } else {
            $placeholders = [];
            foreach (array_values($excludeFlags) as $i => $flag) {
                $key = "flag{$i}";
                $placeholders[] = ":{$key}";
                $params[$key] = $flag;
            }
            $in = implode(',', $placeholders);
            $query = "SELECT comment_id FROM comment WHERE open_chat_id = :openChatId AND flag NOT IN ({$in})";
        }

        return array_column(
            CommentDB::fetchAll($query, $params),
            'comment_id'
        );
    }

    /** @return int[] */
    function getSoftDeletedCommentIds(int $openChatId): array
    {
        return array_column(
            CommentDB::fetchAll(
                "SELECT comment_id FROM comment WHERE open_chat_id = :openChatId AND flag = 5",
                compact('openChatId')
            ),
            'comment_id'
        );
    }

    /** @return int[] */
    function getDeletedCommentIds(int $openChatId): array
    {
        return array_column(
            CommentDB::fetchAll(
                "SELECT comment_id FROM comment WHERE open_chat_id = :openChatId AND flag IN (1, 2, 4, 5)",
                compact('openChatId')
            ),
            'comment_id'
        );
    }

    function shadowDeleteAllComments(int $open_chat_id): int
    {
        $id = compact('open_chat_id');

        // いいね削除
        CommentDB::execute(
            "DELETE FROM `like` WHERE comment_id IN (
                SELECT comment_id FROM comment WHERE open_chat_id = :open_chat_id
            )",
            $id
        );

        // 全コメントをflag=1に更新（flag=2通報,4画像削除は除外、flag=1は既に対象状態）
        return CommentDB::execute(
            "UPDATE comment SET flag = 1 WHERE open_chat_id = :open_chat_id AND flag NOT IN (1, 2, 4)",
            $id
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

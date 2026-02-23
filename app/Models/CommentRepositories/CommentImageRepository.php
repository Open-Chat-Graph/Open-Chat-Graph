<?php

declare(strict_types=1);

namespace App\Models\CommentRepositories;

class CommentImageRepository implements CommentImageRepositoryInterface
{
    function addImages(int $commentId, array $filenames): void
    {
        $query =
            "INSERT INTO
                comment_image (comment_id, filename, sort_order)
            VALUES
                (:comment_id, :filename, :sort_order)";

        foreach ($filenames as $sortOrder => $filename) {
            CommentDB::execute($query, [
                'comment_id' => $commentId,
                'filename' => $filename,
                'sort_order' => $sortOrder,
            ]);
        }
    }

    function getImagesByCommentId(int $commentId): array
    {
        $query =
            "SELECT
                filename, sort_order
            FROM
                comment_image
            WHERE
                comment_id = :comment_id
            ORDER BY
                sort_order";

        return CommentDB::fetchAll($query, ['comment_id' => $commentId]);
    }

    function getImagesByCommentIds(array $commentIds): array
    {
        if (empty($commentIds)) {
            return [];
        }

        $params = [];
        $placeholders = [];
        foreach (array_values($commentIds) as $i => $id) {
            $key = "id_{$i}";
            $placeholders[] = ":{$key}";
            $params[$key] = $id;
        }
        $placeholderStr = implode(',', $placeholders);

        $query =
            "SELECT
                id, comment_id, filename
            FROM
                comment_image
            WHERE
                comment_id IN ({$placeholderStr})
            ORDER BY
                sort_order";

        $rows = CommentDB::fetchAll($query, $params);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['comment_id']][] = [
                'id' => (int) $row['id'],
                'filename' => $row['filename'],
            ];
        }
        return $result;
    }

    function deleteByCommentId(int $commentId): array
    {
        $query =
            "SELECT
                filename
            FROM
                comment_image
            WHERE
                comment_id = :comment_id";

        $filenames = CommentDB::fetchAll($query, ['comment_id' => $commentId], [\PDO::FETCH_COLUMN, 0]);

        if (!empty($filenames)) {
            CommentDB::execute(
                "DELETE FROM comment_image WHERE comment_id = :comment_id",
                ['comment_id' => $commentId]
            );
        }

        return $filenames;
    }

    function getDeletedCommentImages(int $limit = 50, int $offset = 0): array
    {
        $query =
            "SELECT
                ci.id, ci.comment_id, ci.filename
            FROM
                comment_image AS ci
                LEFT JOIN comment AS c ON ci.comment_id = c.comment_id
            WHERE
                c.comment_id IS NULL
                OR c.flag IN (1, 2, 4)
            ORDER BY
                ci.id DESC
            LIMIT
                :offset, :limit";

        return CommentDB::fetchAll($query, compact('limit', 'offset'));
    }

    function getDeletedCommentImageCount(): int
    {
        $query =
            "SELECT
                COUNT(*)
            FROM
                comment_image AS ci
                LEFT JOIN comment AS c ON ci.comment_id = c.comment_id
            WHERE
                c.comment_id IS NULL
                OR c.flag IN (1, 2, 4)";

        return (int) CommentDB::fetchColumn($query);
    }

    function getActiveCommentImages(int $limit = 50, int $offset = 0): array
    {
        $query =
            "SELECT
                ci.id, ci.comment_id, ci.filename,
                c.open_chat_id, c.id AS comment_number
            FROM
                comment_image AS ci
                JOIN comment AS c ON ci.comment_id = c.comment_id
            WHERE
                c.flag NOT IN (1, 2, 4)
            ORDER BY
                ci.id DESC
            LIMIT
                :offset, :limit";

        return CommentDB::fetchAll($query, compact('limit', 'offset'));
    }

    function getActiveCommentImageCount(): int
    {
        $query =
            "SELECT
                COUNT(*)
            FROM
                comment_image AS ci
                JOIN comment AS c ON ci.comment_id = c.comment_id
            WHERE
                c.flag NOT IN (1, 2, 4)";

        return (int) CommentDB::fetchColumn($query);
    }

    function getImageStats(): array
    {
        $query =
            "SELECT
                COUNT(*) AS count
            FROM
                comment_image";

        $count = (int) CommentDB::fetchColumn($query);

        return ['count' => $count];
    }

    function deleteImageById(int $imageId): string|false
    {
        $query = "SELECT filename FROM comment_image WHERE id = :id";
        $filename = CommentDB::fetchColumn($query, ['id' => $imageId]);

        if (!$filename) {
            return false;
        }

        CommentDB::execute("DELETE FROM comment_image WHERE id = :id", ['id' => $imageId]);

        return $filename;
    }

    function getCommentIdByImageId(int $imageId): int|false
    {
        $query = "SELECT comment_id FROM comment_image WHERE id = :id";
        $result = CommentDB::fetchColumn($query, ['id' => $imageId]);

        return $result !== false ? (int) $result : false;
    }

    function getFilenameByImageId(int $imageId): string|false
    {
        $query = "SELECT filename FROM comment_image WHERE id = :id";
        return CommentDB::fetchColumn($query, ['id' => $imageId]);
    }

    function getCommentFlagByFilename(string $filename): int|false
    {
        $query =
            "SELECT
                c.flag
            FROM
                comment_image AS ci
                JOIN comment AS c ON ci.comment_id = c.comment_id
            WHERE
                ci.filename = :filename";

        $result = CommentDB::fetchColumn($query, ['filename' => $filename]);
        return $result !== false ? (int) $result : false;
    }
}

<?php

declare(strict_types=1);

namespace App\Models\CommentRepositories;

use App\Models\CommentRepositories\Enum\CommentLogType;

class CommentLogRepository implements CommentLogRepositoryInterface
{
    function addLog(int $entity_id, CommentLogType $type, string $ip, string $ua, string $data = ''): int
    {
        $query =
            "INSERT INTO
                log (entity_id, type, ip, ua, data)
            VALUES
                (:entity_id, :logType, :ip, :ua, :data)";

        $logType = $type->value;

        return CommentDB::executeAndGetLastInsertId($query, compact(
            'entity_id',
            'logType',
            'ip',
            'ua',
            'data'
        ));
    }

    function findReportLog(int $entity_id, CommentLogType $type, string $data): bool
    {
        $query =
            "SELECT
                id
            FROM
                log
            WHERE
                entity_id = :entity_id
                AND type = :logType
                AND data = :data";

        $logType = $type->value;

        return !!CommentDB::fetchColumn($query, compact(
            'entity_id',
            'logType',
            'data'
        ));
    }

    function findAddCommentLog(int $comment_id): array|false
    {
        $query =
            "SELECT
                ip, ua
            FROM
                log
            WHERE
                entity_id = :comment_id
                AND type = 'AddComment'
            LIMIT 1";

        return CommentDB::fetch($query, compact('comment_id'));
    }
}

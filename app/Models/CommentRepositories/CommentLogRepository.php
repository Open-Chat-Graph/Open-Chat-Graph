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

    function findRecentNamesByUserIdOrIp(string $user_id, string $ip): array
    {
        $query =
            "SELECT DISTINCT c.name
            FROM comment c
            LEFT JOIN log l ON l.entity_id = c.comment_id AND l.type = 'AddComment'
            WHERE (c.user_id = :user_id OR l.ip = :ip)
            AND c.name != ''
            ORDER BY c.comment_id DESC
            LIMIT 10";

        return CommentDB::fetchAll($query, compact('user_id', 'ip'), [\PDO::FETCH_COLUMN, 0]);
    }

    /** @param int[] $commentIds */
    function addAdminLogs(array $commentIds, CommentLogType $type): void
    {
        if (empty($commentIds)) return;

        $data = date('Y-m-d H:i:s');
        $typeValue = $type->value;

        $placeholders = [];
        $params = [];
        foreach ($commentIds as $i => $cid) {
            $placeholders[] = "(:entity_id_{$i}, :type_{$i}, '', '', :data_{$i})";
            $params["entity_id_{$i}"] = $cid;
            $params["type_{$i}"] = $typeValue;
            $params["data_{$i}"] = $data;
        }

        $query = "INSERT INTO `log` (entity_id, type, ip, ua, data) VALUES " . implode(', ', $placeholders);
        CommentDB::execute($query, $params);
    }

    private static function adminTypeInClause(): string
    {
        return implode(',', array_map(fn($t) => "'{$t}'", CommentLogType::adminTypes()));
    }

    function getAdminLogs(int $limit, int $offset): array
    {
        $in = self::adminTypeInClause();
        $query =
            "SELECT l.id, l.entity_id, l.type, l.data,
                    c.open_chat_id, c.name, c.text, c.flag, c.time AS comment_time, c.user_id
            FROM `log` l
            LEFT JOIN comment c ON l.entity_id = c.comment_id
            WHERE l.type IN ({$in})
            ORDER BY l.id DESC
            LIMIT :limit OFFSET :offset";

        return CommentDB::fetchAll($query, compact('limit', 'offset'));
    }

    function getAdminLogCount(): int
    {
        $in = self::adminTypeInClause();
        return (int) CommentDB::fetchColumn(
            "SELECT COUNT(*) FROM `log` WHERE `type` IN ({$in})"
        );
    }

    function getAdminLogDetail(int $logId): array|false
    {
        $in = self::adminTypeInClause();
        $query =
            "SELECT l.id, l.entity_id, l.type, l.data,
                    c.open_chat_id, c.id AS comment_seq_id, c.name, c.text, c.flag, c.time AS comment_time, c.user_id
            FROM `log` l
            LEFT JOIN comment c ON l.entity_id = c.comment_id
            WHERE l.id = :logId AND l.type IN ({$in})";

        return CommentDB::fetch($query, compact('logId'));
    }
}

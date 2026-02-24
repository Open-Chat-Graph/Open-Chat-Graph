<?php

namespace App\Models\CommentRepositories;

use App\Models\CommentRepositories\Enum\CommentLogType;

interface CommentLogRepositoryInterface
{
    function addLog(int $entity_id, CommentLogType $type, string $ip, string $ua, string $data = ''): int;

    function findReportLog(int $entity_id, CommentLogType $type, string $data): bool;

    /** @return array{ ip: string, ua: string }|false */
    function findAddCommentLog(int $comment_id): array|false;

    /** @return string[] */
    function findRecentNamesByUserIdOrIp(string $user_id, string $ip): array;

    /** @param int[] $commentIds */
    function addAdminLogs(array $commentIds, CommentLogType $type): void;

    /**
     * @return array{ id: int, entity_id: int, data: string, open_chat_id: ?int, name: ?string, text: ?string, flag: ?int, comment_time: ?string, user_id: ?string }[]
     */
    function getAdminLogs(int $limit, int $offset): array;

    function getAdminLogCount(): int;

    /**
     * @return array{ id: int, entity_id: int, data: string, open_chat_id: ?int, name: ?string, text: ?string, flag: ?int, comment_time: ?string, user_id: ?string }|false
     */
    function getAdminLogDetail(int $logId): array|false;
}

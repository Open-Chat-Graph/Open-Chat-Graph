<?php

declare(strict_types=1);

namespace App\Models\CommentRepositories;

use App\Models\CommentRepositories\Dto\CommentListApiArgs;
use App\Models\CommentRepositories\Dto\CommentListApi;

class CommentListRepository implements CommentListRepositoryInterface
{
    function findComments(CommentListApiArgs $args): array
    {
        // 1. ページぶんのコメントを確定（comment + log のみ。index駆動で軽い）
        $query =
            "SELECT
                c.id,
                c.comment_id AS commentId,
                CASE
                    WHEN c.flag = 5 THEN 'Anonymous'
                    WHEN c.flag IN (1, 2) AND c.user_id != :user_id THEN 'Anonymous'
                    ELSE c.name
                END AS name,
                CASE
                    WHEN c.flag = 5 THEN ''
                    WHEN c.flag IN (1, 2) AND c.user_id != :user_id THEN ''
                    ELSE c.text
                END AS text,
                c.time,
                c.user_id AS userId,
                CASE
                    WHEN c.flag = 4 THEN 4
                    WHEN c.flag = 5 THEN 5
                    WHEN c.user_id = :user_id THEN 0
                    ELSE c.flag
                END AS flag,
                lg.ip AS logIp,
                lg.ua AS logUa
            FROM
                comment AS c
                LEFT JOIN log AS lg ON lg.entity_id = c.comment_id AND lg.type = 'AddComment'
            WHERE
                c.open_chat_id = :open_chat_id
                AND (c.flag != 1 OR c.user_id = :user_id)
            ORDER BY
                c.comment_id DESC
            LIMIT
                :offset, :limit";

        /** @var CommentListApi[] $comments */
        $comments = CommentDB::fetchAll($query, [
            'user_id' => $args->user_id,
            'open_chat_id' => $args->open_chat_id,
            'offset' => $args->page * $args->limit,
            'limit' => $args->limit,
        ], [\PDO::FETCH_CLASS, CommentListApi::class]);

        if (!$comments) {
            return [];
        }

        // 2. いいね集計はページ内コメントだけにスコープして取得し、マージ
        $commentIds = array_map(fn(CommentListApi $c) => $c->commentId, $comments);
        $likeMap = $this->fetchLikeCounts($commentIds, $args->user_id);

        foreach ($comments as $c) {
            $like = $likeMap[$c->commentId] ?? null;
            if ($like !== null) {
                $c->empathyCount = (int)$like['empathy'];
                $c->insightsCount = (int)$like['insights'];
                $c->negativeCount = (int)$like['negative'];
                $c->voted = $like['voted'] ?? '';
            }
        }

        return $comments;
    }

    /**
     * 指定コメントIDぶんのいいね集計を comment_id => 集計行 のマップで返す
     *
     * @param int[] $commentIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchLikeCounts(array $commentIds, string $user_id): array
    {
        $params = ['user_id' => $user_id];
        $placeholders = [];
        foreach (array_values($commentIds) as $i => $id) {
            $key = "cid_{$i}";
            $placeholders[] = ":{$key}";
            $params[$key] = $id;
        }
        $in = implode(',', $placeholders);

        $query =
            "SELECT
                comment_id,
                COUNT(CASE WHEN type = 'empathy' THEN 1 END) AS empathy,
                COUNT(CASE WHEN type = 'insights' THEN 1 END) AS insights,
                COUNT(CASE WHEN type = 'negative' THEN 1 END) AS negative,
                GROUP_CONCAT(CASE WHEN user_id = :user_id THEN type END) AS voted
            FROM
                `like`
            WHERE
                comment_id IN ({$in})
            GROUP BY
                comment_id";

        $rows = CommentDB::fetchAll($query, $params);

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['comment_id']] = $row;
        }
        return $map;
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
                comment_id,
                user_id
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

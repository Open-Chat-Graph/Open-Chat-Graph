<?php

declare(strict_types=1);

namespace App\Models\CommentRepositories;

use App\Models\CommentRepositories\CommentDB;
use App\Models\Repositories\DB;

class RecentCommentListRepository implements RecentCommentListRepositoryInterface
{
    /**
     * @return array{ id:int,user:string,name:string,img_url:string,description:string,member:int,emblem:int,category:int,time:string }[]
     */
    public function findRecentCommentOpenChatAll(
        int $offset,
        int $limit,
        string $adminId = '',
        string $user_id = '',
        int $open_chat_id = 0,
        string $order = 'DESC',
    ): array {
        $query =
            "SELECT
                open_chat_id,
                time,
                name,
                CASE
                    WHEN user_id = :user_id THEN 0
                    ELSE flag
                END AS flag,
                text
            FROM
                comment
            WHERE
                NOT user_id = :adminId
                AND (flag != 1 OR user_id = :user_id)
                AND (open_chat_id != :open_chat_id OR open_chat_id = 0)
            ORDER BY
                time {$order}
            LIMIT
                :offset, :limit;";

        $comments = CommentDB::fetchAll($query, compact('offset', 'limit', 'adminId', 'user_id', 'open_chat_id'));
        if (empty($comments)) return [];

        $ids = array_unique(array_column($comments, 'open_chat_id'));
        $ids = implode(',', $ids);

        $ocQuery =
            "SELECT
                oc.id,
                oc.name,
                oc.img_url,
                oc.category,
                oc.member,
                oc.emblem
            FROM
                open_chat AS oc
            WHERE
                id IN ({$ids})";


        $oc = DB::fetchAll($ocQuery);

        $idArray = array_column($oc, 'id');

        $result = [];
        foreach ($comments as $el) {
            if ($el['open_chat_id'] === 0) {
                $result[] = [
                    'id' => 0,
                    'user' => ($el['name'] ?: '匿名'),
                    'name' => 'オプチャグラフとは？',
                    'img_url' => fileUrl('assets/icon-192x192.png'),
                    'emblem' => 0,
                    'description' => $el['text'],
                    'time' => $el['time'],
                    'member' => 0,
                    'category' => 0,
                ];
            }

            $key = array_search($el['open_chat_id'], $idArray);
            if ($key === false) continue;

            $result[] = [
                'id' => $el['open_chat_id'],
                'user' => $el['flag'] === 0 ? ($el['name']  ?: '匿名') : '***',
                'name' => $oc[$key]['name'],
                'img_url' => $oc[$key]['img_url'],
                'emblem' => $oc[$key]['emblem'],
                'description' => $el['flag'] === 0 ? $el['text'] : '',
                'time' => $el['time'],
                'member' => $oc[$key]['member'],
                'category' => $oc[$key]['category'],
            ];
        }

        return $result;
    }

    public function getLatestCommentTime(): string|false
    {
        $query = "SELECT MAX(time) FROM comment";
        return CommentDB::fetchColumn($query) ?? false;
    }

    public function getRecordCount(
        string $adminId = '',
        string $user_id = '',
        int $open_chat_id = 0,
    ): int {
        $query =
            "SELECT
                COUNT(*)
            FROM
                comment
            WHERE
                NOT user_id = :adminId
                AND (flag != 1 OR user_id = :user_id)
                AND (open_chat_id != :open_chat_id OR open_chat_id = 0)";

        return CommentDB::fetchColumn($query, compact('adminId', 'user_id', 'open_chat_id'));
    }
}

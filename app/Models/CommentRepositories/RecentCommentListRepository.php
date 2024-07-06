<?php

declare(strict_types=1);

namespace App\Models\CommentRepositories;

use App\Models\CommentRepositories\CommentDB;
use Shadow\DB;

class RecentCommentListRepository implements RecentCommentListRepositoryInterface
{
    /**
     * @return array{ id:int,name:string,img_url:string,description:string,member:int,emblem:int,category:int,time:string }[]
     */
    public function findRecentCommentOpenChat(int $offset, int $limit): array
    {
        $query =
            "WITH RankedComments AS (
                SELECT
                    open_chat_id,
                    time,
                    ROW_NUMBER() OVER (
                        PARTITION BY open_chat_id
                        ORDER BY
                            time DESC
                    ) AS rn,
                    text,
                    name,
                    flag
                FROM
                    comment
                WHERE
                    open_chat_id != 0
                    AND flag != 1
            )
            SELECT
                open_chat_id,
                time,
                text,
                name,
                flag
            FROM
                RankedComments
            WHERE
                rn = 1
            ORDER BY
                time DESC
            LIMIT
                :offset, :limit;";

        $comments = CommentDB::fetchAll($query, compact('offset', 'limit'));

        $ids = array_column($comments, 'open_chat_id');
        $ids = implode(',', $ids);

        $ocQuery =
            "SELECT
                oc.id,
                oc.name,
                oc.local_img_url AS img_url,
                --oc.category,
                oc.emblem
            FROM
                open_chat AS oc
            WHERE
                id IN ({$ids})
            ORDER BY FIELD(id, {$ids})";


        $oc = DB::fetchAll($ocQuery);

        $result = [];
        foreach ($oc as $i => $el) {
            $result[] = $el + ['time' => $comments[$i]['time'], 'description' => $comments[$i]['flag'] !== 1 ? $comments[$i]['text'] : '削除されたコメント😇'];
        }

        return $result;
    }

    /**
     * @return array{ id:int,name:string,img_url:string,description:string,member:int,emblem:int,category:int,time:string }[]
     */
    public function findRecentCommentOpenChatAll(int $offset, int $limit, string $adminId = '', string $user_id = ''): array
    {
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
            ORDER BY
                time DESC
            LIMIT
                :offset, :limit;";

        $comments = CommentDB::fetchAll($query, compact('offset', 'limit', 'adminId', 'user_id'));

        $ids = array_unique(array_column($comments, 'open_chat_id'));
        $ids = implode(',', $ids);

        $ocQuery =
            "SELECT
                oc.id,
                oc.name,
                oc.local_img_url AS img_url,
                --oc.category,
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
                    'name' => 'オプチャグラフについて',
                    'img_url' => 'siteicon',
                    'emblem' => 0,
                    'description' => $el['text'],
                    'time' => $el['time']
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
                'time' => $el['time']
            ];
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace App\Models\CommentRepositories;

use App\Config\AppConfig;
use App\Models\CommentRepositories\CommentDB;
use Shared\MimimalCmsConfig;

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
        $order = $order === 'ASC' ? 'ASC' : 'DESC';
        // comment DB と open_chat DB は同一 MySQL サーバー上にあるためクロスDB JOIN できる
        $mainDb = AppConfig::$dbName[MimimalCmsConfig::$urlRoot];

        $query =
            "SELECT
                c.comment_id,
                c.open_chat_id,
                c.time,
                c.name,
                CASE
                    WHEN c.flag = 5 THEN 5
                    WHEN c.user_id = :user_id THEN 0
                    ELSE c.flag
                END AS flag,
                c.text,
                oc.name AS oc_name,
                oc.img_url AS oc_img_url,
                oc.category AS oc_category,
                oc.member AS oc_member,
                oc.emblem AS oc_emblem
            FROM
                comment AS c
                LEFT JOIN `{$mainDb}`.`open_chat` AS oc ON oc.id = c.open_chat_id
            WHERE
                NOT c.user_id = :adminId
                AND (c.flag != 1 OR c.user_id = :user_id)
                AND (c.open_chat_id != :open_chat_id OR c.open_chat_id = 0)
                AND (c.open_chat_id = 0 OR oc.id IS NOT NULL)
            ORDER BY
                c.time {$order}
            LIMIT
                :offset, :limit;";

        $comments = CommentDB::fetchAll($query, compact('offset', 'limit', 'adminId', 'user_id', 'open_chat_id'));

        $result = [];
        foreach ($comments as $el) {
            if ($el['open_chat_id'] === 0) {
                $result[] = [
                    'id' => 0,
                    'comment_id' => $el['comment_id'],
                    'user' => in_array($el['flag'], [0, 4]) ? ($el['name'] ?: '匿名') : '***',
                    'name' => 'オプチャグラフとは？',
                    'img_url' => fileUrl('assets/icon-192x192.png'),
                    'emblem' => 0,
                    'description' => in_array($el['flag'], [0, 4]) ? $el['text'] : '',
                    'time' => $el['time'],
                    'member' => 0,
                    'category' => 0,
                ];
                continue;
            }

            $result[] = [
                'id' => $el['open_chat_id'],
                'comment_id' => $el['comment_id'],
                'user' => in_array($el['flag'], [0, 4]) ? ($el['name']  ?: '匿名') : '***',
                'name' => $el['oc_name'],
                'img_url' => $el['oc_img_url'],
                'emblem' => $el['oc_emblem'],
                'description' => in_array($el['flag'], [0, 4]) ? $el['text'] : '',
                'time' => $el['time'],
                'member' => $el['oc_member'],
                'category' => $el['oc_category'],
            ];
        }

        return $result;
    }

    public function getLatestCommentTime(): string|false
    {
        $query = "SELECT GREATEST(
            COALESCE((SELECT MAX(time) FROM comment), '0'),
            COALESCE((SELECT data FROM `log` WHERE `type` LIKE 'Admin%' ORDER BY id DESC LIMIT 1), '0')
        )";
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

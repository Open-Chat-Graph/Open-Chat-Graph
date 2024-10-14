<?php

namespace App\Models\CommentRepositories;

interface RecentCommentListRepositoryInterface
{
    /**
     * @return array{ id:int,user:string,name:string,img_url:string,description:string,member:int,emblem:int,category:int,time:string }[]
     */
    public function findRecentCommentOpenChatAll(int $offset, int $limit, string $adminId = '', string $user_id = ''): array;
}

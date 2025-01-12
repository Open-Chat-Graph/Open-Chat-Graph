<?php

declare(strict_types=1);

use App\Models\CommentRepositories\RecentCommentListRepository;
use PHPUnit\Framework\TestCase;

class RecentCommentListRepositoryTest extends TestCase
{
    private RecentCommentListRepository $inst;

    public function test()
    {
        $this->inst = app(RecentCommentListRepository::class);

        $res = $this->inst->findRecentCommentOpenChat(0, 5);
        debug($res);

        $this->assertTrue(true);
    }
}

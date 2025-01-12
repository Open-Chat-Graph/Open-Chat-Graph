<?php

declare(strict_types=1);

use App\Models\CommentRepositories\CommentListRepository;
use App\Models\CommentRepositories\Dto\CommentListApiArgs;
use PHPUnit\Framework\TestCase;

class CommentListRepositoryTest extends TestCase
{
    private CommentListRepository $inst;

    public function test()
    {
        $this->inst = app(CommentListRepository::class);

        $args = new CommentListApiArgs;

        $args->open_chat_id = 1234;
        $args->user_id = 'test';
        $args->limit = 2;
        $args->page = 1;

        $r = $this->inst->findComments($args);
        debug($r);

        $this->assertTrue(true);
    }
}

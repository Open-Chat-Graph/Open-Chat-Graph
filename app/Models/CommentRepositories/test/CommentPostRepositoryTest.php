<?php

declare(strict_types=1);

use App\Models\CommentRepositories\CommentPostRepository;
use App\Models\CommentRepositories\Dto\CommentPostApiArgs;
use PHPUnit\Framework\TestCase;

class CommentPostRepositoryTest extends TestCase
{
    private CommentPostRepository $inst;

    public function testPost()
    {
        $this->inst = app(CommentPostRepository::class);

        $args = new CommentPostApiArgs;

        $args->open_chat_id = 1234;
        $args->user_id = 'test';
        $args->text = 'testest';
        $args->name = 'testkun';

        $this->inst->addComment($args);

        $this->assertTrue(true);
    }
}

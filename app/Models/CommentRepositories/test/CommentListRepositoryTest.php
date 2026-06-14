<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Models/CommentRepositories/test/CommentListRepositoryTest.php
 */

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

        $args = new CommentListApiArgs(
            page: 1,
            limit: 2,
            open_chat_id: 1234,
            user_id: 'test',
        );

        $r = $this->inst->findComments($args);
        debug($r);

        $this->assertTrue(true);
    }
}

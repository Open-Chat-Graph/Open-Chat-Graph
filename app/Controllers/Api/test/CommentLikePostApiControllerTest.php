<?php

declare(strict_types=1);

/**
 * docker compose exec app vendor/bin/phpunit app/Controllers/Api/test/CommentLikePostApiControllerTest.php
 */

use App\Controllers\Api\CommentLikePostApiController;
use App\Models\CommentRepositories\CommentLogRepositoryInterface;
use App\Models\CommentRepositories\LikePostRepositoryInterface;
use App\Services\Auth\AuthInterface;
use PHPUnit\Framework\TestCase;

class CommentLikePostApiControllerTest extends TestCase
{
    public function testAdd()
    {
        $stub = $this->createStub(AuthInterface::class);
        $stub->method('verifyCookieUserId')->willReturn('test2');

        $inst = app(CommentLikePostApiController::class, [
            'auth' => $stub,
            'likePostRepository' => app(LikePostRepositoryInterface::class),
            'commentLogRepository' => app(CommentLogRepositoryInterface::class),
        ]);

        $res = $inst->add(5, 'insights');

        debug($res);

        $this->assertTrue(true);
    }

    public function testDelete()
    {
        $stub = $this->createStub(AuthInterface::class);
        $stub->method('verifyCookieUserId')->willReturn('test2');

        $inst = app(CommentLikePostApiController::class, [
            'auth' => $stub,
            'likePostRepository' => app(LikePostRepositoryInterface::class),
            'commentLogRepository' => app(CommentLogRepositoryInterface::class),
        ]);

        $res = $inst->delete(5);

        debug($res);

        $this->assertTrue(true);
    }
}

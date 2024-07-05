<?php

declare(strict_types=1);

use App\Controllers\Api\CommentListApiController;
use App\Models\CommentRepositories\CommentListRepositoryInterface;
use App\Services\Auth\Auth;
use PHPUnit\Framework\TestCase;

class CommentListApiControllerTest extends TestCase
{
    private CommentListApiController $inst;
    public function test()
    {
        $this->inst = app(CommentListApiController::class);

        $stub = $this->createStub(Auth::class);
        $stub->method('loginCookieUserId')->willReturn('test1');

        $res = $this->inst->index(
            app(CommentListRepositoryInterface::class),
            $stub,
            1,
            2,
            1234
        );

        debug($res);

        $this->assertTrue(true);
    }
}

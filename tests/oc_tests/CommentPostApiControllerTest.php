<?php

declare(strict_types=1);

use App\Controllers\Api\CommentPostApiController;
use App\Models\CommentRepositories\CommentLogRepositoryInterface;
use App\Models\CommentRepositories\CommentPostRepositoryInterface;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Services\Auth\Auth;
use PHPUnit\Framework\TestCase;

class CommentPostApiControllerTest extends TestCase
{
    private CommentPostApiController $inst;
    public function test()
    {
        $this->inst = app(CommentPostApiController::class);

        $stub = $this->createStub(Auth::class);
        $stub->method('verifyCookieUserId')->willReturn('test_user_id');

        $res = $this->inst->index(
            app(CommentPostRepositoryInterface::class),
            app(CommentLogRepositoryInterface::class),
            app(OpenChatPageRepositoryInterface::class),
            $stub,
            2,
            'テストユーザー',
            'テスト本文'
        );

        debug($res);

        $this->assertTrue(true);
    }
}

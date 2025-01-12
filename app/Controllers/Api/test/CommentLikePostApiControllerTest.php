<?php

declare(strict_types=1);

use App\Controllers\Api\CommentLikePostApiController;
use App\Services\Auth\Auth;
use PHPUnit\Framework\TestCase;

class CommentLikePostApiControllerTest extends TestCase
{
    private CommentLikePostApiController $inst;
    public function test()
    {
        $stub = $this->createStub(Auth::class);
        $stub->method('verifyCookieUserId')->willReturn('test2');
        
        $this->inst = app(CommentLikePostApiController::class, ['auth' => $stub]);

        $res = $this->inst->add(5, 'insights');

        debug($res);

        $this->assertTrue(true);
    }

    public function testDelete()
    {
        $stub = $this->createStub(Auth::class);
        $stub->method('verifyCookieUserId')->willReturn('test2');

        $this->inst = app(CommentLikePostApiController::class, ['auth' => $stub]);

        $res = $this->inst->delete(5);

        debug($res);

        $this->assertTrue(true);
    }
}

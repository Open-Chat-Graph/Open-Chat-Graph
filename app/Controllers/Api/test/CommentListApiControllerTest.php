<?php

declare(strict_types=1);

/**
 * docker compose exec app su -s /bin/bash www-data -c "vendor/bin/phpunit app/Controllers/Api/test/CommentListApiControllerTest.php"
 */

use App\Controllers\Api\CommentListApiController;
use App\Models\CommentRepositories\CommentImageRepositoryInterface;
use App\Models\CommentRepositories\CommentListRepositoryInterface;
use App\Models\CommentRepositories\CommentPostRepositoryInterface;
use App\Services\Auth\AuthInterface;
use PHPUnit\Framework\TestCase;

class CommentListApiControllerTest extends TestCase
{
    public function test()
    {
        $inst = app(CommentListApiController::class);

        $stub = $this->createStub(AuthInterface::class);
        $stub->method('loginCookieUserId')->willReturn('test1');

        $res = $inst->index(
            app(CommentListRepositoryInterface::class),
            app(CommentPostRepositoryInterface::class),
            app(CommentImageRepositoryInterface::class),
            $stub,
            1,
            2,
            1234
        );

        debug($res);

        $this->assertTrue(true);
    }
}

<?php

declare(strict_types=1);

/**
 * docker compose exec app vendor/bin/phpunit app/Controllers/Api/test/CommentPostApiControllerTest.php
 */

use App\Controllers\Api\CommentPostApiController;
use App\Models\CommentRepositories\CommentLogRepositoryInterface;
use App\Models\CommentRepositories\CommentPostRepositoryInterface;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Services\Auth\AuthInterface;
use App\Services\Auth\GoogleReCaptcha;
use App\Services\Storage\FileStorageInterface;
use PHPUnit\Framework\TestCase;

class CommentPostApiControllerTest extends TestCase
{
    public function test()
    {
        $inst = app(CommentPostApiController::class);

        $authStub = $this->createStub(AuthInterface::class);
        $authStub->method('verifyCookieUserId')->willReturn('test_user_id');

        $recaptchaStub = $this->createStub(GoogleReCaptcha::class);
        $recaptchaStub->method('validate')->willReturn(0.9);

        $res = $inst->index(
            app(CommentPostRepositoryInterface::class),
            app(CommentLogRepositoryInterface::class),
            app(OpenChatPageRepositoryInterface::class),
            $authStub,
            $recaptchaStub,
            app(FileStorageInterface::class),
            'dummy_token',
            2,
            'テストユーザー',
            'テスト本文'
        );

        debug($res);

        $this->assertTrue(true);
    }
}

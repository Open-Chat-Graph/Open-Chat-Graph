<?php

declare(strict_types=1);

use App\Models\CommentRepositories\Dto\LikeApiArgs;
use App\Models\CommentRepositories\Dto\LikeDeleteApiArgs;
use App\Models\CommentRepositories\Dto\LikePostApiArgs;
use App\Models\CommentRepositories\Enum\LikeBtnType;
use App\Models\CommentRepositories\LikePostRepository;
use PHPUnit\Framework\TestCase;

class LikePostRepositoryTest extends TestCase
{
    private LikePostRepository $inst;

/*     public function test()
    {
        $this->inst = app(LikePostRepository::class);

        $args = new LikePostApiArgs;

        $args->comment_id = 3;
        $args->user_id = 'test4';
        $args->type = LikeBtnType::Negative;

        $res = $this->inst->addLike($args);
        debug($res);

        $this->assertTrue(true);
    } */

/*     public function testDelete()
    {
        $this->inst = app(LikePostRepository::class);

        $args = new LikeDeleteApiArgs;

        $args->comment_id = 3;
        $args->user_id = 'test8';

        $res = $this->inst->deleteLike($args);
        debug($res);

        $this->assertTrue(true);
    } */

    public function test()
    {
        $this->inst = app(LikePostRepository::class);

        $args = new LikeApiArgs;

        $args->comment_id = 3;
        $args->user_id = 'test4';

        $res = $this->inst->getLikeRecord($args);
        debug($res);

        $this->assertTrue(true);
    }
}

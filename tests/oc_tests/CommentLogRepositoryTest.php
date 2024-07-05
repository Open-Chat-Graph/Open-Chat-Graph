<?php

declare(strict_types=1);

use App\Models\CommentRepositories\CommentLogRepository;
use App\Models\CommentRepositories\Enum\CommentLogType;
use PHPUnit\Framework\TestCase;

class CommentLogRepositoryTest extends TestCase
{
    private CommentLogRepository $inst;

    public function test()
    {
        $this->inst = app(CommentLogRepository::class);

        $this->inst->addLog(1234, 'test', CommentLogType::Like, '213.323.323.323', 'ua windows');

        $this->assertTrue(true);
    }
}

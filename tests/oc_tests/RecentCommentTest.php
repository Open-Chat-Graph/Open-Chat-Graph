<?php

declare(strict_types=1);

use App\Views\RecentComment;
use PHPUnit\Framework\TestCase;

class RecentCommentTest extends TestCase
{
    private RecentComment $instance;

    protected function setUp(): void
    {
        $this->instance = app(RecentComment::class);
    }

    public function test()
    {
        debug($this->instance->getAllOrderByRegistrationDate(1, 1));
        $this->assertTrue(true);
    }
}

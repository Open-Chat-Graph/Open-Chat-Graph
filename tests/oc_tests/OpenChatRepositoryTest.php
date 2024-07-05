<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Models\Repositories\OpenChatRepositoryInterface;
use PHPUnit\Framework\TestCase;

class OpenChatRepositoryTest extends TestCase
{
    public function test()
    {
        $res = app(OpenChatRepositoryInterface::class)->getOpenChatIdAllByCreatedAtDate('2024-02-14');
        debug($res[count($res) - 1]);
        $this->assertTrue(true);
    }
}

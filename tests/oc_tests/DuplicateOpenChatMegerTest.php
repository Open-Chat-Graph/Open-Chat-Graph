<?php

use PHPUnit\Framework\TestCase;
use Shadow\DB;
use App\Config\AppConfig;
use App\Services\OpenChat\DuplicateOpenChatMeger;

class DuplicateOpenChatMegerTest extends TestCase
{
    public function test()
    {
        $merger = app(DuplicateOpenChatMeger::class);

        $res = $merger->mergeDuplicateOpenChat();
        debug($res);
        $this->assertTrue(true);
    }
}

<?php

declare(strict_types=1);

use App\Services\Cron\SyncOpenChat;
use PHPUnit\Framework\TestCase;

class SyncOpenChatTest extends TestCase
{
    public function test()
    {
        /**
         * @var SyncOpenChat $syncOpenChat
         */
        $syncOpenChat = app(SyncOpenChat::class);
        $syncOpenChat->handle();

        addCronLog('End');
        $this->assertTrue(true);
    }
}

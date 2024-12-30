<?php

use App\Services\Cron\ParallelDownloadOpenChat;
use App\Services\OpenChat\OpenChatApiDataParallelDownloader;
use PHPUnit\Framework\TestCase;

class ParallelDownloadOpenChatTest extends TestCase
{
    public function test()
    {
        set_time_limit(3600 * 10);

        /**
         * @var ParallelDownloadOpenChat $inst
         */
        $inst = app(ParallelDownloadOpenChat::class);

        OpenChatApiDataParallelDownloader::disableKillFlag();
        $result = $inst->handle([['type' => 'rising', 'category' => 6], ['type' => 'ranking', 'category' => 6]]);

        var_dump($result);

        $this->assertIsInt(0);
    }
}

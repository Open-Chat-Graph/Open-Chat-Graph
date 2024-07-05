<?php

use PHPUnit\Framework\TestCase;
use App\Services\OpenChat\OpenChatApiDataParallelDownloader;

class OpenChatApiDataParallelDownloaderrTest extends TestCase
{
    public function test()
    {
        set_time_limit(3600 * 10);

        /**
         * @var OpenChatApiDataParallelDownloader $openChatDataDbApiMerger
         */
        $openChatDataDbApiMerger = app(OpenChatApiDataParallelDownloader::class);

        $result = $openChatDataDbApiMerger->fetchOpenChatApiRanking('0', '0');

        var_dump($result);

        $this->assertIsInt(0);
    }
}

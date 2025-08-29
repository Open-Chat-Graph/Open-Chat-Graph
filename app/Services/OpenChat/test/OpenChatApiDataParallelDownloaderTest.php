<?php

use App\Services\OpenChat\Enum\RankingType;
use PHPUnit\Framework\TestCase;
use App\Services\OpenChat\OpenChatApiDataParallelDownloader;

class OpenChatApiDataParallelDownloaderTest extends TestCase
{
    public function testfetchOpenChatApi()
    {
        set_time_limit(3600 * 10);

        /**
         * @var OpenChatApiDataParallelDownloader $openChatDataDbApiMerger
         */
        $openChatDataDbApiMerger = app(OpenChatApiDataParallelDownloader::class);

        $result = $openChatDataDbApiMerger->fetchOpenChatApi(RankingType::Ranking, '16');

        var_dump($result);

        $this->assertIsInt(0);
    }
}

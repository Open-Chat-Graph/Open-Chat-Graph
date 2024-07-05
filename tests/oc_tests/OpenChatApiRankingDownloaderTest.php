<?php

use PHPUnit\Framework\TestCase;
use App\Services\OpenChat\Crawler\FiberOpenChatApiRankingDownloader;

class FiberOpenChatApiRankingDownloaderTest extends TestCase
{
    public function testfetchSaveOpenChatRankingApiData()
    {
        /**
         * @var FiberOpenChatApiRankingDownloader $openChatApiRankingDataDownloader
         */
        $openChatApiRankingDataDownloader = app(FiberOpenChatApiRankingDownloader::class);

        $res = $openChatApiRankingDataDownloader->fetchOpenChatApiRankingAllConcurrently(1, 21, function ($apiData) {
            var_dump($apiData);
        });

        var_dump($res);

        $this->assertTrue($res > 0);
    }
}

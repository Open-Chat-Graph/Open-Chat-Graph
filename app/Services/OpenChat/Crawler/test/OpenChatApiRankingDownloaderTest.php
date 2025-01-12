<?php

use PHPUnit\Framework\TestCase;
use App\Services\OpenChat\Crawler\OpenChatApiRankingDownloaderProcess;

class OpenChatApiRankingDownloaderTest extends TestCase
{
    public function testfetchSaveOpenChatRankingApiData()
    {
        /**
         * @var OpenChatApiRankingDownloaderProcess $openChatApiRankingDataDownloader
         */
        $openChatApiRankingDataDownloader = app(OpenChatApiRankingDownloaderProcess::class);

        $res = $openChatApiRankingDataDownloader->fetchOpenChatApiRankingProcess(2, '3', function ($apiData) {
            debug($apiData);
        });

        debug($res);

        $this->assertTrue(!!$res);
    }
}

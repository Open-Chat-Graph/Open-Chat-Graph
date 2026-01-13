<?php

use PHPUnit\Framework\TestCase;
use App\Services\OpenChat\Crawler\OpenChatApiDownloaderProcessFactory;
use App\Services\OpenChat\Enum\RankingType;

class OpenChatApiRankingDownloaderTest extends TestCase
{
    public function testfetchSaveOpenChatRankingApiData()
    {
        /**
         * @var OpenChatApiDownloaderProcessFactory $factory
         */
        $factory = app(OpenChatApiDownloaderProcessFactory::class);
        $openChatApiRankingDataDownloader = $factory->createDownloader(RankingType::Ranking);

        $res = $openChatApiRankingDataDownloader->fetchOpenChatApiRanking('2', function ($apiData) {
            debug($apiData);
        });

        debug($res);

        $this->assertTrue(!!$res);
    }
}

<?php

use App\Services\OpenChat\Enum\RankingType;
use App\Services\OpenChat\OpenChatApiDataParallelDownloader;
use App\Services\OpenChat\OpenChatApiDbMergerWithParallelDownloader;
use PHPUnit\Framework\TestCase;

class OpenChatApiDbMergerWithParallelDownloaderTest extends TestCase
{
    public function test()
    {
        /**
         * @var OpenChatApiDbMergerWithParallelDownloader $inst
         */
        $inst = app(OpenChatApiDbMergerWithParallelDownloader::class);

        OpenChatApiDataParallelDownloader::disableKillFlag();
        //$result = $inst->fetchOpenChatApiRankingAll();
        $result = $inst->mergeProcess(RankingType::Ranking, 6);

        var_dump($result);

        $this->assertIsInt(0);
    }
}

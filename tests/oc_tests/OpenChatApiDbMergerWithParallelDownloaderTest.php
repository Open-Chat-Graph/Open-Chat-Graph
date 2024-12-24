<?php

use App\Services\OpenChat\Enum\RankingType;
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

        OpenChatApiDbMergerWithParallelDownloader::setKillFlagFalse();
        //$result = $inst->fetchOpenChatApiRankingAll();
        $inst->mergeProcess(RankingType::Ranking, 6);

        $this->assertIsInt(0);
    }
}

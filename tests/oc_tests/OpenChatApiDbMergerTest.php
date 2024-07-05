<?php

use PHPUnit\Framework\TestCase;
use App\Services\OpenChat\OpenChatApiDbMerger;

class OpenChatApiDbMergerTest extends TestCase
{
    public function test()
    {
        set_time_limit(3600 * 10);

        /**
         * @var OpenChatApiDbMerger $openChatDataDbApiMerger
         */
        $openChatDataDbApiMerger = app(OpenChatApiDbMerger::class);

        $result = $openChatDataDbApiMerger->fetchOpenChatApiRankingAll();

        var_dump($result);

        $this->assertIsInt(0);
    }
}

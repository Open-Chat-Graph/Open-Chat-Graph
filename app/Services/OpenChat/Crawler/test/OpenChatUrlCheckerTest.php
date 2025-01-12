<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use App\Services\OpenChat\Crawler\OpenChatUrlChecker;

class OpenChatUrlCheckerTest extends TestCase
{
    public function test()
    {
        /**
         * @var OpenChatUrlChecker $urlchecker
         */
        $urlchecker = app(OpenChatUrlChecker::class);
        $result = $urlchecker->isOpenChatUrlAvailable('w9fHQlpnotrnqHukFT6IQF6ZCXR7XKUoya4yrg');

        debug($result);

        $this->assertTrue(true);
    }
}

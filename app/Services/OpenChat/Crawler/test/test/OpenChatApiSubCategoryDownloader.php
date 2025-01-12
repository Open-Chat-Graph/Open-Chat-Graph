<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use App\Services\OpenChat\Crawler\OpenChatApiSubCategoryDownloader;

class OpenChatApiSubCategoryDownloaderTest extends TestCase
{
    public function test()
    {
        /**
         * @var OpenChatApiSubCategoryDownloader $test
         */
        $test = app(OpenChatApiSubCategoryDownloader::class);
        $test->fetchOpenChatApiSubCategoriesAll(fn($data) => debug($data));

        $this->assertTrue(true);
    }
}

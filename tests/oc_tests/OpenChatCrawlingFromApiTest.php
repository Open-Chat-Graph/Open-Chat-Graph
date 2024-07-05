<?php

declare(strict_types=1);

namespace Shadow\File;

use PHPUnit\Framework\TestCase;
use App\Services\OpenChat\OpenChatCrawlingFromApi;

class OpenChatCrawlingFromApiTest extends TestCase
{
    public function testcaluclatemaxExecuteNum(): void
    {
        /**
         * @var OpenChatCrawlingFromApi $updater
         */
        $updater = app(OpenChatCrawlingFromApi::class);
        $result = $updater->caluclatemaxExecuteNum();

        debug($result);
        $this->assertIsInt($result);
    }

    public function testopenChatCrawlingFromPage(): void
    {
        /**
         * @var OpenChatCrawlingFromApi $updater
         */
        $updater = app(OpenChatCrawlingFromApi::class);
        $updater->openChatCrawling(100);

        $this->assertTrue(true);
    }
}

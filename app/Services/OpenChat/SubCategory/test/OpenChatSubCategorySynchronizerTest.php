<?php

declare(strict_types=1);

use App\Config\AppConfig;
use PHPUnit\Framework\TestCase;
use App\Config\OpenChatCrawlerConfig;
use App\Services\OpenChat\SubCategory\OpenChatSubCategorySynchronizer;

class OpenChatSubCategorySynchronizerTest extends TestCase
{
    public function test()
    {
        overwriteUrlRoot('/th');

        /**
         * @var OpenChatSubCategorySynchronizer $test
         */
        $test = app(OpenChatSubCategorySynchronizer::class);
        $test->syncSubCategoriesAll();

        $file = json_decode(file_get_contents(AppConfig::$OPEN_CHAT_SUB_CATEGORIES_FILE_PATH), true);
        debug($file);

        $this->assertTrue(is_array($file) && !empty($file));
    }
}

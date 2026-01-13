<?php

declare(strict_types=1);

use App\Config\AppConfig;
use PHPUnit\Framework\TestCase;
use App\Services\Crawler\Config\OpenChatCrawlerConfig;
use App\Services\OpenChat\SubCategory\OpenChatSubCategorySynchronizer;

class OpenChatSubCategorySynchronizerTest extends TestCase
{
    public function test()
    {
        /**
         * @var OpenChatSubCategorySynchronizer $test
         */
        $test = app(OpenChatSubCategorySynchronizer::class);
        $test->syncSubCategoriesAll();

        $file = json_decode(file_get_contents(AppConfig::getStorageFilePath('openChatSubCategories')), true);
        debug($file);

        $this->assertTrue(is_array($file) && !empty($file));
    }
}

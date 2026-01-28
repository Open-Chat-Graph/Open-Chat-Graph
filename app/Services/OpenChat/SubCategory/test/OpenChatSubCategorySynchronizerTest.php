<?php

declare(strict_types=1);

use App\Services\Storage\FileStorageInterface;
use PHPUnit\Framework\TestCase;
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

        /** @var FileStorageInterface $fileStorage */
        $fileStorage = app(FileStorageInterface::class);
        $file = json_decode($fileStorage->getContents('@openChatSubCategories'), true);
        debug($file);

        $this->assertTrue(is_array($file) && !empty($file));
    }
}

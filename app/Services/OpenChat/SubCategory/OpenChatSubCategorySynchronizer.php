<?php

declare(strict_types=1);

namespace App\Services\OpenChat\SubCategory;

use App\Services\OpenChat\Crawler\OpenChatApiSubCategoryDownloader;
use App\Services\Storage\FileStorageInterface;
use Shadow\Kernel\Validator;
use App\Config\AppConfig;

class OpenChatSubCategorySynchronizer
{
    private array $fetchedSubcategories = [];

    function __construct(
        private OpenChatApiSubCategoryDownloader $openChatApiSubCategoryDownloader,
        private FileStorageInterface $fileStorage,
    ) {}

    /**
     * @return array [CategoryName => int] サブカテゴリが存在するカテゴリ名 => サブカテゴリ数
     *
     * @throws \RuntimeException
     */
    function syncSubCategoriesAll(): array
    {
        $result = $this->openChatApiSubCategoryDownloader->fetchOpenChatApiSubCategoriesAll(
            $this->saveSubCategories(...)
        );

        $result && $this->fileStorage->safeFileRewrite(
            '@openChatSubCategories',
            json_encode($this->fetchedSubcategories, JSON_UNESCAPED_UNICODE)
        );

        return $result;
    }

    /**
     * @param array $data ['subcategory' => string, 'categoryId' => int][]
     * 
     * @throws \RuntimeException
     */
    private function saveSubCategories(array $data)
    {
        $categoryId = 0;
        $subcategories = [];
        foreach ($data as $subcategoryArray) {
            if (!$categoryId && Validator::num($subcategoryArray['categoryId'], min: 1)) {
                $categoryId = $subcategoryArray['categoryId'];
            }

            $subcategory = Validator::str($subcategoryArray['subcategory'] ?? false);
            if ($subcategory) {
                $subcategories[] = $subcategory;
            }
        }

        $this->fetchedSubcategories[$categoryId] = $subcategories;
    }
}

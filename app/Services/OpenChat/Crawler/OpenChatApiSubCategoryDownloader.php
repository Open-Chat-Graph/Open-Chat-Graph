<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Crawler;

use App\Services\Crawler\CrawlerFactory;
use App\Config\OpenChatCrawlerConfig;
use App\Config\AppConfig;

class OpenChatApiSubCategoryDownloader
{
    function __construct(
        private CrawlerFactory $crawlerFactory
    ) {
    }

    /**
     * @return array subcategories ['id' => int, 'subcategory' => string, 'categoryId' => int][]
     * 
     * @throws \RuntimeException
     */
    function fetchOpenChatApiSubCategory(string $category): array|false
    {
        $url = OpenChatCrawlerConfig::generateOpenChatApiRankigDataUrl($category, '0');
        $ua = OpenChatCrawlerConfig::USER_AGENT;

        $response = $this->crawlerFactory->createCrawler($url, $ua, getCrawler: false);
        if (!$response) {
            throw new \RuntimeException("データ取得エラー: {$url}");
        }

        $apiData = json_decode($response, true);
        if (!is_array($apiData)) {
            throw new \RuntimeException("JSONデコードエラー: {$url}");
        }

        $subcategories = $apiData['squaresByCategory'][0]['subcategories'] ?? false;
        if (!is_array($subcategories)) {
            return false;
        }

        $count = count($subcategories);
        if ($count < 1) {
            false;
        }

        return $subcategories;
    }

    /**
     * @param \Closure $callback (array['id' => int, 'subcategory' => string, 'categoryId' => int][] $subcategories) => void
     * 
     * @return array [CategoryName => int] サブカテゴリが存在するカテゴリ名 => サブカテゴリ数からなる連想配列
     */
    function fetchOpenChatApiSubCategoriesAll(\Closure $callback): array
    {
        $result = [];
        foreach (AppConfig::$OPEN_CHAT_CATEGORY as $name => $category) {
            if ($category === 0) {
                continue;
            }

            $subcategories = $this->fetchOpenChatApiSubCategory((string)$category);
            if (!$subcategories) {
                continue;
            }

            $callback($subcategories);

            $count = count($subcategories);
            if ($count) {
                $result[$name] = $count;
            }
        }

        return $result;
    }
}

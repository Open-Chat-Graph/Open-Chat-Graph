<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Crawler;

use App\Services\Crawler\CrawlerFactory;
use App\Config\OpenChatCrawlerConfigInterface;
use App\Config\AppConfig;
use Shared\MimimalCmsConfig;

class OpenChatApiSubCategoryDownloader
{
    function __construct(
        private CrawlerFactory $crawlerFactory,
        private OpenChatCrawlerConfigInterface $config
    ) {
    }

    /**
     * @return array subcategories ['id' => int, 'subcategory' => string, 'categoryId' => int][]
     * 
     * @throws \RuntimeException
     */
    function fetchOpenChatApiSubCategory(string $category): array|false
    {
        $url = $this->config->generateOpenChatApiRankingDataUrl($category, '');
        $headers = $this->config->getOpenChatApiOcDataFromEmidDownloaderHeader()[MimimalCmsConfig::$urlRoot];
        $ua = $this->config->getUserAgent();

        $response = $this->crawlerFactory->createCrawler($url, $ua, getCrawler: false, customHeaders: $headers);
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
        foreach (AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] as $name => $category) {
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

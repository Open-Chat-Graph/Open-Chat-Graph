<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Crawler;

use App\Services\Crawler\CrawlerFactory;
use App\Config\OpenChatCrawlerConfig;
use App\Services\OpenChat\Dto\OpenChatApiFromEmidDtoFactory;
use App\Services\OpenChat\Dto\OpenChatDto;

class OpenChatApiFromEmidDownloader
{
    function __construct(
        private CrawlerFactory $crawlerFactory,
        private OpenChatApiFromEmidDtoFactory $openChatApiFromEmidDtoFactory
    ) {
    }

    /**
     * @return array|false 取得したオープンチャット
     * 
     * @throws \RuntimeException
     */
    private function fetchFromEmid(string $emid): array|false
    {
        $url = OpenChatCrawlerConfig::generateOpenChatApiOcDataFromEmidUrl($emid);
        $headers = OpenChatCrawlerConfig::$OPEN_CHAT_API_OC_DATA_FROM_EMID_DOWNLOADER_HEADER;
        $ua = OpenChatCrawlerConfig::USER_AGENT;

        $response = $this->crawlerFactory->createCrawler($url, $ua, customHeaders: $headers, getCrawler: false);

        if (!$response) {
            return false;
        }

        $responseArray = json_decode($response, true);
        if (!is_array($responseArray)) {
            return false;
        }

        return $responseArray;
    }

    /**
     *@throws \RuntimeException
     */
    function fetchOpenChatDto(string $emid): OpenChatDto|false
    {
        $response = $this->fetchFromEmid($emid);
        if (!$response) {
            return false;
        }

        return $this->openChatApiFromEmidDtoFactory->validateAndMapToOpenChatApiFromEmidDto($response);
    }
}

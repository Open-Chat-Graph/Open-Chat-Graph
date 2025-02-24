<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Crawler;

use App\Services\Crawler\CrawlerFactory;
use App\Config\OpenChatCrawlerConfig;
use Shadow\Kernel\Validator;
use Shared\MimimalCmsConfig;

abstract class AbstractOpenChatApiRankingDownloaderProcess
{
    /**
     * @var \Closure $callableGenerateUrl `$callableGenerateUrl(string $category, string $ct)`
     */
    protected \Closure $callableGenerateUrl;

    function __construct(
        protected CrawlerFactory $crawlerFactory
    ) {}

    /**
     * @return array{ 0: string|false, 1: int }|false
     */
    function fetchOpenChatApiRankingProcess(string $category, string $ct, \Closure $callback): array|false
    {
        $generateUrl = $this->callableGenerateUrl;
        $url = $generateUrl($category, $ct);
        $headers = OpenChatCrawlerConfig::OPEN_CHAT_API_OC_DATA_FROM_EMID_DOWNLOADER_HEADER[MimimalCmsConfig::$urlRoot];
        $ua = OpenChatCrawlerConfig::USER_AGENT;

        // 暫定的に500に対応
        try {
            $response = $this->crawlerFactory->createCrawler($url, $ua, getCrawler: false, customHeaders: $headers);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 500) {
                return false;
            }

            throw $e;
        }

        if (!$response) {
            return false;
        }

        $apiData = json_decode($response, true);
        if (!is_array($apiData)) {
            return false;
        }

        $squares = $apiData['squaresByCategory'][0]['squares'] ?? false;
        if (!is_array($squares)) {
            return false;
        }

        $count = count($squares);
        if ($count < 1) {
            return false;
        }

        $callback($apiData, $category);

        $responseCt = Validator::str($apiData['continuationTokenMap'][$category] ?? false);

        return [$responseCt, $count];
    }
}

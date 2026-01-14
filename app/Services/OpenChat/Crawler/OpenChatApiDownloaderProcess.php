<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Crawler;

use App\Services\Crawler\CrawlerFactory;
use App\Services\Crawler\Config\OpenChatCrawlerConfigInterface;
use App\Services\Cron\Utility\CronUtility;
use App\Services\OpenChat\Enum\RankingType;
use Shadow\Kernel\Validator;
use Shared\MimimalCmsConfig;

/**
 * OpenChat API ランキング/急上昇データ取得プロセス
 */
class OpenChatApiDownloaderProcess
{
    function __construct(
        private CrawlerFactory $crawlerFactory,
        private OpenChatCrawlerConfigInterface $config,
        private RankingType $rankingType
    ) {}

    /**
     * @return array{ 0: string|false, 1: int }|false
     */
    function fetchOpenChatApiRankingProcess(string $category, string $ct, \Closure $callback): array|false
    {
        $url = match ($this->rankingType) {
            RankingType::Ranking => $this->config->generateOpenChatApiRankingDataUrl($category, $ct),
            RankingType::Rising => $this->config->generateOpenChatApiRisingDataUrl($category, $ct),
        };

        $headers = $this->config->getOpenChatApiOcDataFromEmidDownloaderHeader()[MimimalCmsConfig::$urlRoot];
        $ua = $this->config->getUserAgent();

        // 暫定的に500に対応
        try {
            $response = $this->crawlerFactory->createCrawler($url, $ua, getCrawler: false, customHeaders: $headers);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 500) {
                CronUtility::addVerboseCronLog("[警告] HTTPエラー 500 発生によりスキップ: " . $e->getMessage());
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

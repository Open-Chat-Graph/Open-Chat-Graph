<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Crawler;

use App\Services\Crawler\CrawlerFactory;
use App\Config\OpenChatCrawlerConfigInterface;
use App\Services\OpenChat\Dto\OpenChatApiFromEmidDtoFactory;
use App\Services\OpenChat\Dto\OpenChatDto;
use Shared\MimimalCmsConfig;

class OpenChatApiFromEmidDownloader
{
    function __construct(
        private CrawlerFactory $crawlerFactory,
        private OpenChatApiFromEmidDtoFactory $openChatApiFromEmidDtoFactory,
        private OpenChatCrawlerConfigInterface $config
    ) {
    }

    /**
     * @return array|false 取得したオープンチャット
     * 
     * @throws \RuntimeException
     */
    private function fetchFromEmid(string $emid): array|false
    {
        $url = $this->config->generateOpenChatApiOcDataFromEmidUrl($emid);
        $headers = $this->config->getOpenChatApiOcDataFromEmidDownloaderHeader()[MimimalCmsConfig::$urlRoot];
        $ua = $this->config->getUserAgent();

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

        // レスポンスにinvitationTicketとsquareを含める構造に変換
        if (isset($response['invitationTicket']) && isset($response['square'])) {
            $data = [
                'invitationTicket' => $response['invitationTicket'],
                'square' => $response['square'],
            ];
        } else {
            // 古い形式の場合（後方互換性）
            $data = $response;
        }

        return $this->openChatApiFromEmidDtoFactory->validateAndMapToOpenChatApiFromEmidDto($data);
    }
}

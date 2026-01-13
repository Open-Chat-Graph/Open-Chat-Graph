<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Crawler;

use App\Config\OpenChatCrawlerConfig;
use App\Config\AppConfig;
use App\Services\Crawler\CrawlerFactory;

class OpenChatUrlChecker
{
    function __construct(
        private CrawlerFactory $crawlerFactory
    ) {
    }

    /**
     * Check if the specified invitationTicket is available by sending a HEAD request.
     *
     * @param string $invitationTicket The invitationTicket to check.
     * @return bool Returns true if the URL is available (HTTP status code 200), false if not (HTTP status code 404).
     * @throws \RuntimeException If an unexpected HTTP status code is encountered.
     */
    public function isOpenChatUrlAvailable(string $invitationTicket): bool
    {
        $url = OpenChatCrawlerConfig::LINE_INTERNAL_URL . $invitationTicket;
        $ua = OpenChatCrawlerConfig::USER_AGENT;

        return $this->crawlerFactory->createCrawler($url, $ua, method: 'HEAD', getCrawler: false) !== false;
    }
}

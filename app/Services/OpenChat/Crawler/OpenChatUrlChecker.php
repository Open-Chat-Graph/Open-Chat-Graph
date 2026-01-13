<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Crawler;

use App\Config\OpenChatCrawlerConfigInterface;
use App\Services\Crawler\CrawlerFactory;

class OpenChatUrlChecker
{
    function __construct(
        private CrawlerFactory $crawlerFactory,
        private OpenChatCrawlerConfigInterface $config
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
        $url = $this->config->getLineInternalUrl() . $invitationTicket;
        $ua = $this->config->getUserAgent();

        return $this->crawlerFactory->createCrawler($url, $ua, method: 'HEAD', getCrawler: false) !== false;
    }
}

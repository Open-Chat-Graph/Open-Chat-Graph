<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Crawler;

use App\Config\OpenChatCrawlerConfig;
use App\Services\Crawler\CrawlerFactory;

class OpenChatApiRisingDownloaderProcess extends AbstractOpenChatApiRankingDownloaderProcess
{
    function __construct(
        protected CrawlerFactory $crawlerFactory
    ) {
        $this->callableGenerateUrl = OpenChatCrawlerConfig::generateOpenChatApiRisingDataUrl(...);
    }
}

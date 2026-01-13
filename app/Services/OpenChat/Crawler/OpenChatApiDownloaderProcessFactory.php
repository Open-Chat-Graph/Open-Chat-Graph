<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Crawler;

use App\Services\Crawler\CrawlerFactory;
use App\Services\Crawler\Config\OpenChatCrawlerConfigInterface;
use App\Services\OpenChat\Enum\RankingType;

/**
 * OpenChatApiRankingDownloader のファクトリー
 */
class OpenChatApiDownloaderProcessFactory
{
    function __construct(
        private CrawlerFactory $crawlerFactory,
        private OpenChatCrawlerConfigInterface $config
    ) {}

    /**
     * 指定されたランキングタイプのダウンローダーを作成
     */
    function createDownloader(RankingType $type): OpenChatApiRankingDownloader
    {
        $process = new OpenChatApiDownloaderProcess(
            $this->crawlerFactory,
            $this->config,
            $type
        );

        return new OpenChatApiRankingDownloader($process);
    }
}

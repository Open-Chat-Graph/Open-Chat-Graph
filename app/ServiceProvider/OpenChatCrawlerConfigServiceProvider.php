<?php

declare(strict_types=1);

namespace App\ServiceProvider;

use App\Config\AppConfig;
use App\Services\Crawler\Config\OpenChatCrawlerConfigInterface;
use App\Services\Crawler\Config\OpenChatCrawlerConfig;
use App\Services\Crawler\Config\MockOpenChatCrawlerConfig;

class OpenChatCrawlerConfigServiceProvider implements ServiceProviderInterface
{
    public function register(): void
    {
        if (AppConfig::$isMockEnvironment) {
            app()->bind(OpenChatCrawlerConfigInterface::class, MockOpenChatCrawlerConfig::class);
        } else {
            app()->bind(OpenChatCrawlerConfigInterface::class, OpenChatCrawlerConfig::class);
        }
    }
}

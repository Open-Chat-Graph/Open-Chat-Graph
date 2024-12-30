<?php

use PHPUnit\Framework\TestCase;
use App\Services\Crawler\CrawlerFactory;

class CrawlerFactoryTest extends TestCase
{
    public function testOpenChatApiFromEmidDownloader()
    {
        /**
         * @var CrawlerFactory $crawlerFactory
         */
        $crawlerFactory = app(CrawlerFactory::class);

        // ターゲットのURL
        $url = 'https://openchat.line.me/api/square/-FqPIR-ZPxM-3FzBpaW2wNwsGiDL5dw7JV3z-kksg9Er_7RajR3Xh1RYA7A?limit=1';

        // ヘッダー情報を設定
        $headers = [
            "X-Line-Seo-User: xeef6bb40587e35dc62588c6a75759069",
        ];

        $res = $crawlerFactory->createCrawler($url, 'ua', customHeaders: $headers, getCrawler: false);
        var_dump($res);

        $this->assertIsInt(0);
    }
}

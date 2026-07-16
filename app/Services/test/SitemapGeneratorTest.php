<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/test/SitemapGeneratorTest.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Services\SitemapGenerator;

class SitemapGeneratorTest extends TestCase
{
    private SitemapGenerator $site;

    public function testGeneratedUrlsAreCanonicalAndUnique(): void
    {
        $this->site = app(SitemapGenerator::class);
        $this->site->generate();

        $urls = [];
        foreach (glob(SitemapGenerator::SITEMAP_DIR . 'ja/sitemap-*.xml') ?: [] as $file) {
            $xml = simplexml_load_file($file);
            self::assertNotFalse($xml, $file);
            foreach ($xml->url as $entry) {
                $url = (string)$entry->loc;
                self::assertStringNotContainsString('?', $url);
                self::assertStringNotContainsString('#', $url);
                self::assertArrayNotHasKey($url, $urls, "Duplicate sitemap URL: {$url}");
                $urls[$url] = true;
            }
        }

        self::assertNotEmpty($urls);
        self::assertArrayHasKey('https://openchat-review.me/privacy', $urls);
        self::assertArrayHasKey('https://openchat-review.me/terms', $urls);
        self::assertArrayHasKey('https://openchat-review.me/ranking', $urls);
    }
}

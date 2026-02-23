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

    public function test()
    {
        $this->site = app(SitemapGenerator::class);
        $this->site->generate();
        $this->assertTrue(true);
    }
}

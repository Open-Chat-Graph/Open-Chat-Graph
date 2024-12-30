<?php

declare(strict_types=1);

use App\Services\OpenChat\Utility\OpenChatServicesUtility;
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

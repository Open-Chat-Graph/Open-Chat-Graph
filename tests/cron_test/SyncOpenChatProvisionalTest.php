<?php

declare(strict_types=1);

use App\Config\AppConfig;
use PHPUnit\Framework\TestCase;

class SyncOpenChatProvisionalTest extends TestCase
{
    public function test()
    {
        $arg = escapeshellarg('/th');

        $path = AppConfig::ROOT_PATH . 'cron_crawling_provisional.php';
        exec(PHP_BINARY . " {$path} {$arg}");
        
        $this->assertTrue(true);
    }
}

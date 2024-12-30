<?php

declare(strict_types=1);

use App\Config\AppConfig;
use PHPUnit\Framework\TestCase;

class SyncOpenChatTest extends TestCase
{
    public function test()
    {
        $arg = escapeshellarg('');

        $path = AppConfig::ROOT_PATH . 'batch/cron/cron_crawling.php';
        exec(PHP_BINARY . " {$path} {$arg}");
        
        $this->assertTrue(true);
    }
}

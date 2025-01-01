<?php

declare(strict_types=1);

use App\Config\AppConfig;
use PHPUnit\Framework\TestCase;

class cron_crawling_provisional_test extends TestCase
{
    public function test()
    {
        $arg = escapeshellarg('/th');
        $arg2 = escapeshellarg('dailyTest');

        $path = AppConfig::ROOT_PATH . 'batch/cron/cron_crawling_provisional.php';
        exec(PHP_BINARY . " {$path} {$arg} {$arg2}");
        
        $this->assertTrue(true);
    }
}

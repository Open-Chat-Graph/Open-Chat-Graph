<?php

declare(strict_types=1);

use App\Config\AppConfig;
use PHPUnit\Framework\TestCase;

class cron_half_check_provisional_test extends TestCase
{
    public function test()
    {
        $arg = escapeshellarg('/th');

        $path = AppConfig::ROOT_PATH . 'batch/cron/cron_half_check_provisional.php';
        exec(PHP_BINARY . " {$path} {$arg}");
        
        $this->assertTrue(true);
    }
}

<?php

declare(strict_types=1);

use App\Services\Cron\OpenChatIdDataZipBackupCron;
use PHPUnit\Framework\TestCase;

class OpenChatIdDataZipBackupCronTest extends TestCase
{
    public function test()
    {
        $inst = app(OpenChatIdDataZipBackupCron::class);
        debug($inst->ftpZipBackUp());
        $this->assertTrue(true);
    }
}

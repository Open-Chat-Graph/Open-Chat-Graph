<?php

declare(strict_types=1);

use App\Services\Cron\CommentDataZipBackupCron;
use PHPUnit\Framework\TestCase;

class CommentDataZipBackupCronTest extends TestCase
{
    public function test()
    {
        $inst = app(CommentDataZipBackupCron::class);
        debug($inst->saveBackup());
        $this->assertTrue(true);
    }
}

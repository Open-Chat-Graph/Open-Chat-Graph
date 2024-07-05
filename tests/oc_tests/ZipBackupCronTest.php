<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Services\Cron\ZipBackupCron;
use App\Services\Cron\CronJson\SQLiteBackupCronState;

class ZipBackupCronTest extends TestCase
{
    public function test()
    {
        /**
         * @var ZipBackupCron $cron
         */
        $cron = app(ZipBackupCron::class);

        $cron->ftpZipBackUp(app(SQLiteBackupCronState::class));

        $this->assertTrue(true);
    }
}

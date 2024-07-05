<?php

declare(strict_types=1);

use App\Services\Cron\CronJson\SQLiteBackupCronState;
use PHPUnit\Framework\TestCase;

class SQLiteBackupCronStateTest extends TestCase
{
    public function test()
    {
        /**
         * @var SQLiteBackupCronState $state
         */
        $state = app(SQLiteBackupCronState::class);

        debug($state->getBackups());

        $this->assertTrue(!$state->isActive());
    }
}

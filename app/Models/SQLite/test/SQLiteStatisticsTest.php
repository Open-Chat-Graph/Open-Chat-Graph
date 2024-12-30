<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Models\SQLite\SQLiteStatistics;

class SQLiteStatisticsTest extends TestCase
{
    public function test()
    {
        /**
         * @var SQLiteStatistics $db
         */
        $db = app(SQLiteStatistics::class);

        $result = $db->fetchAll('SELECT * FROM statistics WHERE id = 1');

        debug($result);

        $this->assertTrue(true);
    }
}

<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Models/SQLite/test/SQLiteStatisticsTest.php
 */

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

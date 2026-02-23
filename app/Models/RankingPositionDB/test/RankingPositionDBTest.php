<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Models/RankingPositionDB/test/RankingPositionDBTest.php
 */

declare(strict_types=1);

use App\Models\RankingPositionDB\RankingPositionDB;
use PHPUnit\Framework\TestCase;

class RankingPositionDBTest extends TestCase
{
    public function test()
    {
        debug(RankingPositionDB::fetchAll("SELECT * FROM ranking limit 1"));

        $this->assertTrue(true);
    } 
}

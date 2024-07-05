<?php

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

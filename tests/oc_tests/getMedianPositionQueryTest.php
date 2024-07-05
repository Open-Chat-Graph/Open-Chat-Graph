<?php

declare(strict_types=1);

use App\Models\SQLite\Repositories\RankingPosition\SqliteRankingPositionHourRepository;
use PHPUnit\Framework\TestCase;

class getMedianPositionQueryTest extends TestCase
{
    private SqliteRankingPositionHourRepository $repo;

    public function test()
    {
        $this->repo = app(SqliteRankingPositionHourRepository::class);

        $date = new \DateTime('2024-02-09');
        $date->setTimeZone(new \DateTimeZone('Asia/Tokyo'));

        $r = $this->repo->getDaliyRanking($date, false);

        debug($r[count($r) - 30000]);

        $this->assertTrue(true);
    }
}

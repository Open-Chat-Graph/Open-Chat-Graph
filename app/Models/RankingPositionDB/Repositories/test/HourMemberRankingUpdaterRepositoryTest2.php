<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Models/RankingPositionDB/Repositories/test/HourMemberRankingUpdaterRepositoryTest2.php
 */

declare(strict_types=1);

use App\Models\RankingPositionDB\Repositories\HourMemberRankingUpdaterRepository;
use PHPUnit\Framework\TestCase;

class HourMemberRankingUpdaterRepositoryTest extends TestCase
{
    private HourMemberRankingUpdaterRepository $instance;

    public function test()
    {
        $this->instance = app(HourMemberRankingUpdaterRepository::class);

        $result = $this->instance->buildRankingData(new \DateTime('2024-02-17 05:30:00'));

        debug($result[0]);

        $this->assertTrue(true);
    }
}

<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Models\RankingPositionDB\Repositories\RankingPositionHourRepository;
use App\Services\RankingPosition\Store\RisingPositionStore;
use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

class RankingPositionHourRepositoryTest extends TestCase
{
    private RankingPositionHourRepository $repo;

    public function testgetMedianPosition()
    {
        $this->repo = app(RankingPositionHourRepository::class);

        $date = new \DateTime('2024-09-04');
        $date->setTimeZone(new \DateTimeZone('Asia/Tokyo'));

        $r = $this->repo->getDaliyRanking($date, false);

        debug($r[count($r) - 30000]);

        $this->assertTrue(true);
    }

    public function testdelete()
    {
        /**
         * @var RankingPositionHourRepository $repo
         */
        $repo = app(RankingPositionHourRepository::class);

        // 指定の日時より以前のデータを削除
        $result = $repo->dalete(new DateTime('2024-02-17 07:30:00'));

        debug($result);

        $this->assertTrue(true);
    }

    public function testinsertFromDtoArray()
    {
        /**
         * @var RisingPositionStore $test
         */
        $test = app(RisingPositionStore::class);

        /**
         * @var RankingPositionHourRepository $repo
         */
        $repo = app(RankingPositionHourRepository::class);

        foreach (AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] as $category) {
            $result = $repo->insertHourMemberFromDtoArray(...$test->getStorageData((string)$category));
            debug($result);
        }

        $this->assertTrue(true);
    }

    public function testgetMinRankingHour()
    {
        /**
         * @var RankingPositionHourRepository $repo
         */
        $repo = app(RankingPositionHourRepository::class);

        $result = $repo->getDailyRising(new \DateTime());

        debug(array_slice($result, 0, 10));

        $this->assertTrue(true);
    }

    public function testgetTotalCount()
    {
        /**
         * @var RankingPositionHourRepository $repo
         */
        $repo = app(RankingPositionHourRepository::class);

        $result = $repo->getTotalCount(new \DateTime());

        debug($result);

        $this->assertTrue(true);
    }
}

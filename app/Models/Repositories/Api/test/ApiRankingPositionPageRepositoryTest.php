<?php

declare(strict_types=1);

use App\Models\Repositories\Api\ApiRankingPositionPageRepository;
use App\Models\Repositories\RankingPosition\Dto\RankingPositionPageRepoDto;
use App\Services\OpenChat\Enum\RankingType;
use PHPUnit\Framework\TestCase;

class ApiRankingPositionPageRepositoryTest extends TestCase
{
    private ApiRankingPositionPageRepository $repository;
    private const TEST_OPENCHAT_ID = 1;
    private const TEST_CATEGORY = 28;

    protected function setUp(): void
    {
        $this->repository = new ApiRankingPositionPageRepository();
    }

    public function testGetDailyPositionRanking()
    {
        $result = $this->repository->getDailyPosition(
            RankingType::Ranking,
            self::TEST_OPENCHAT_ID,
            self::TEST_CATEGORY
        );
        
        $this->assertInstanceOf(RankingPositionPageRepoDto::class, $result);
        $this->assertIsArray($result->time);
        $this->assertIsArray($result->position);
        $this->assertIsArray($result->totalCount);
        
        if (!empty($result->time)) {
            $this->assertCount(count($result->time), $result->position);
            $this->assertCount(count($result->time), $result->totalCount);
            
            foreach ($result->position as $position) {
                $this->assertIsInt($position);
            }
            
            foreach ($result->totalCount as $count) {
                $this->assertIsInt($count);
            }
        }
        
        $this->assertTrue(true);
    }

    public function testGetDailyPositionRising()
    {
        $result = $this->repository->getDailyPosition(
            RankingType::Rising,
            self::TEST_OPENCHAT_ID,
            self::TEST_CATEGORY
        );
        
        $this->assertInstanceOf(RankingPositionPageRepoDto::class, $result);
        $this->assertIsArray($result->time);
        $this->assertIsArray($result->position);
        $this->assertIsArray($result->totalCount);
        
        if (!empty($result->time)) {
            $this->assertCount(count($result->time), $result->position);
            $this->assertCount(count($result->time), $result->totalCount);
            
            foreach ($result->position as $position) {
                $this->assertIsInt($position);
            }
            
            foreach ($result->totalCount as $count) {
                $this->assertIsInt($count);
            }
        }
        
        $this->assertTrue(true);
    }

    public function testGetFinalRankingPosition()
    {
        $result = $this->repository->getFinalRankingPosition(
            self::TEST_OPENCHAT_ID,
            self::TEST_CATEGORY
        );
        
        if ($result !== false) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('time', $result);
            $this->assertArrayHasKey('position', $result);
            $this->assertArrayHasKey('total_count_ranking', $result);
            
            $this->assertIsString($result['time']);
            $this->assertIsInt($result['position']);
            $this->assertIsInt($result['total_count_ranking']);
        } else {
            $this->assertFalse($result);
        }
        
        $this->assertTrue(true);
    }
}
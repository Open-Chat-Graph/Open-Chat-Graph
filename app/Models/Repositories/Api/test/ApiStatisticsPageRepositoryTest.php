<?php

declare(strict_types=1);

use App\Models\Repositories\Api\ApiStatisticsPageRepository;
use PHPUnit\Framework\TestCase;

class ApiStatisticsPageRepositoryTest extends TestCase
{
    private ApiStatisticsPageRepository $repository;
    private const TEST_ID = 3;

    protected function setUp(): void
    {
        $this->repository = new ApiStatisticsPageRepository();
    }

    public function testGetDailyMemberStatsDateAsc()
    {
        $result = $this->repository->getDailyMemberStatsDateAsc(self::TEST_ID);
        
        $this->assertIsArray($result);
        
        if (!empty($result)) {
            $firstRecord = $result[0];
            $this->assertArrayHasKey('date', $firstRecord);
            $this->assertArrayHasKey('member', $firstRecord);
        }
    }
}
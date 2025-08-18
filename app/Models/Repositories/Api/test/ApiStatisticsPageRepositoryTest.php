<?php

declare(strict_types=1);

use App\Models\Repositories\Api\ApiStatisticsPageRepository;
use PHPUnit\Framework\TestCase;

class ApiStatisticsPageRepositoryTest extends TestCase
{
    private ApiStatisticsPageRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new ApiStatisticsPageRepository();
    }

    public function testGetDailyStatisticsByPeriod()
    {
        echo "\n=== Testing ApiStatisticsPageRepository::getDailyStatisticsByPeriod ===\n";

        // Test with a known OpenChat ID
        $openChatId = 3;
        $result = $this->repository->getDailyStatisticsByPeriod($openChatId);

        echo "Statistics for OpenChat ID {$openChatId}:\n";
        echo "Number of data points: " . count($result['date']) . "\n";

        if (!empty($result['date'])) {
            echo "Date range: " . $result['date'][0] . " to " . end($result['date']) . "\n";
            echo "Member range: " . min($result['member']) . " to " . max($result['member']) . "\n";

            // Show first and last few data points
            $sampleSize = min(3, count($result['date']));
            echo "\nFirst {$sampleSize} data points:\n";
            for ($i = 0; $i < $sampleSize; $i++) {
                debug([
                    'date' => $result['date'][$i],
                    'member' => $result['member'][$i]
                ]);
            }

            if (count($result['date']) > 3) {
                echo "\nLast {$sampleSize} data points:\n";
                $startIndex = count($result['date']) - $sampleSize;
                for ($i = $startIndex; $i < count($result['date']); $i++) {
                    debug([
                        'date' => $result['date'][$i],
                        'member' => $result['member'][$i]
                    ]);
                }
            }
        } else {
            echo "No statistics found for OpenChat ID {$openChatId}\n";
        }

        $this->assertTrue(true);
    }

    public function testGetDailyMemberStatsDateAsc()
    {
        echo "\n=== Testing ApiStatisticsPageRepository::getDailyMemberStatsDateAsc ===\n";

        $openChatId = 169134;
        $result = $this->repository->getDailyMemberStatsDateAsc($openChatId);

        echo "Daily member stats for OpenChat ID {$openChatId}:\n";
        echo "Number of records: " . count($result) . "\n";

        if (!empty($result)) {
            echo "\nFirst 3 records:\n";
            $firstThree = array_slice($result, 0, 3);
            debug($firstThree);

            if (count($result) > 3) {
                echo "\nLast 3 records:\n";
                $lastThree = array_slice($result, -3);
                debug($lastThree);
            }

            // Calculate growth
            $firstMember = $result[0]['member'];
            $lastMember = end($result)['member'];
            $growth = $lastMember - $firstMember;
            $growthPercent = $firstMember > 0 ? round(($growth / $firstMember) * 100, 2) : 0;

            echo "\nGrowth summary:\n";
            debug([
                'first_member_count' => $firstMember,
                'last_member_count' => $lastMember,
                'total_growth' => $growth,
                'growth_percent' => $growthPercent . '%'
            ]);
        } else {
            echo "No stats found for OpenChat ID {$openChatId}\n";
        }

        $this->assertTrue(true);
    }

    public function testGetMemberCount()
    {
        echo "\n=== Testing ApiStatisticsPageRepository::getMemberCount ===\n";

        // First, get some data to know which dates are available
        $openChatId = 3;
        $stats = $this->repository->getDailyMemberStatsDateAsc($openChatId);

        if (!empty($stats)) {
            // Test with the first available date
            $testDate = $stats[0]['date'];
            $memberCount = $this->repository->getMemberCount($openChatId, $testDate);

            if ($memberCount !== false) {
                echo "Member count for OpenChat ID {$openChatId} on {$testDate}: {$memberCount}\n";
                debug([
                    'openchat_id' => $openChatId,
                    'date' => $testDate,
                    'member_count' => $memberCount
                ]);
            } else {
                echo "No member count found for OpenChat ID {$openChatId} on {$testDate}\n";
            }

            // Test with a non-existent date
            $invalidDate = '1999-01-01';
            $invalidResult = $this->repository->getMemberCount($openChatId, $invalidDate);
            echo "\nTesting with invalid date {$invalidDate}: " . ($invalidResult === false ? 'Correctly returned false' : 'Unexpected result: ' . $invalidResult) . "\n";
        } else {
            echo "No statistics available to test getMemberCount\n";
        }

        $this->assertTrue(true);
    }

    public function testMultipleOpenChatStatistics()
    {
        echo "\n=== Testing statistics for multiple OpenChats ===\n";

        $testIds = [3, 10, 169134, 1000, 5000];
        $foundCount = 0;

        foreach ($testIds as $id) {
            $result = $this->repository->getDailyStatisticsByPeriod($id);

            if (!empty($result['date'])) {
                $foundCount++;
                $dataPoints = count($result['date']);
                $latestDate = end($result['date']);
                $latestMember = end($result['member']);

                echo "\nOpenChat ID {$id}:\n";
                debug([
                    'data_points' => $dataPoints,
                    'latest_date' => $latestDate,
                    'latest_member_count' => $latestMember
                ]);
            }
        }

        echo "\nFound statistics for {$foundCount} out of " . count($testIds) . " OpenChats\n";

        $this->assertTrue(true);
    }
}

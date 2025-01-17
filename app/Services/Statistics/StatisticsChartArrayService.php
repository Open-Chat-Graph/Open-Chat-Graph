<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\Repositories\Statistics\StatisticsPageRepositoryInterface;
use App\Services\Statistics\Dto\StatisticsChartDto;

class StatisticsChartArrayService
{
    private const MAX_RETRIES = 5;

    function __construct(
        private StatisticsPageRepositoryInterface $statisticsPageRepository,
    ) {}

    function buildStatisticsChartArray(int $open_chat_id): StatisticsChartDto|false
    {
        $memberStats = $this->getMemberStatsWithRetry($open_chat_id);

        if (!$memberStats) {
            return false;
        }

        $dto = new StatisticsChartDto($memberStats[0]['date'], $memberStats[count($memberStats) - 1]['date']);

        return $this->generateChartArray(
            $dto,
            $this->generateDateArray($dto->startDate, $dto->endDate),
            $memberStats
        );
    }

    /**
     * 日毎のメンバー数の統計を取得する
     * 
     * @return array{ date: string, member: int }[] date: Y-m-d
     */
    private function getMemberStatsWithRetry(int $open_chat_id, int $maxRetries = self::MAX_RETRIES): array
    {
        $attempts = 0;

        while ($attempts < $maxRetries) {
            try {
                return $this->statisticsPageRepository->getDailyMemberStatsDateAsc($open_chat_id);
            } catch (\PDOException $e) {
                if (strpos($e->getMessage(), 'database disk image is malformed') === false) {
                    throw $e;
                }

                usleep(100000); // Wait for 0.1 seconds
                $attempts++;
            }
        }

        throw new \RuntimeException("Failed to get member stats after {$maxRetries} attempts.");
    }

    /**  
     *  @param string $startDate `Y-m-d`
     *  @return string[]
     */
    private function generateDateArray(string $startDate, string $endDate): array
    {
        $first = new \DateTime($startDate);
        $interval = $first->diff(new \DateTime($endDate))->days;

        // データが8日分未満の場合は追加する
        /* if ($interval < 8) {
            $mod = 7 - $interval;
            $first->modify("-{$mod} day");
            $interval = $first->diff(new \DateTime($endDate))->days;
        } */

        $dateArray = [];
        $i = 0;

        while ($i <= $interval) {
            $dateArray[] = $first->format('Y-m-d');
            $first->modify('+1 day');
            $i++;
        }

        return $dateArray;
    }

    /**
     * @param string[] $dateArray
     * @param array{ date:string, member:int }[] $memberStats
     */
    private function generateChartArray(StatisticsChartDto $dto, array $dateArray, array $memberStats): StatisticsChartDto
    {
        $getMemberStatsCurDate = fn(int $key): string => $memberStats[$key]['date'] ?? '';

        $curKeyMemberStats = 0;
        $memberStatsCurDate = $getMemberStatsCurDate(0);

        foreach ($dateArray as $date) {
            $matchMemberStats = $memberStatsCurDate === $date;

            $member = null;
            if ($matchMemberStats) {
                $member = $memberStats[$curKeyMemberStats]['member'];
                $curKeyMemberStats++;
                $memberStatsCurDate = $getMemberStatsCurDate($curKeyMemberStats);
            }

            $dto->addValue(
                $date,
                $member,
            );
        }

        return $dto;
    }
}

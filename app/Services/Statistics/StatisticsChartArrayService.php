<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\Repositories\Statistics\StatisticsOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsPageRepositoryInterface;
use App\Services\Statistics\Dto\StatisticsChartDto;

class StatisticsChartArrayService
{
    function __construct(
        private StatisticsPageRepositoryInterface $statisticsPageRepository,
        private StatisticsOhlcRepositoryInterface $statisticsOhlcRepository,
    ) {}

    /**
     * 日毎のメンバー数の統計を取得する
     *
     * @return array{ date: string, member: int }[] date: Y-m-d
     */
    function buildStatisticsChartArray(int $open_chat_id): StatisticsChartDto|false
    {
        $memberStats = $this->statisticsPageRepository->getDailyMemberStatsDateAsc($open_chat_id);

        if (!$memberStats) {
            return false;
        }

        $dto = new StatisticsChartDto($memberStats[0]['date'], $memberStats[count($memberStats) - 1]['date']);

        $this->generateChartArray(
            $dto,
            $this->generateDateArray($dto->startDate, $dto->endDate),
            $memberStats
        );

        $this->setOhlcAvailability($dto, $open_chat_id);

        return $dto;
    }

    /**
     * 期間タブ毎のローソク足(OHLC)データ有無を設定する
     *
     * 各期間タブはグラフ末尾からの日数ウィンドウ（1週間=8件, 1ヶ月=31件）を表示するため、
     * ウィンドウ内のOHLC件数で判定する
     * - 1週間: ウィンドウ内の全日分が揃っている場合のみ有効
     * - 1ヶ月: ウィンドウ内の半分以上の日にあれば有効
     * - 全期間: 1件でもあれば有効
     */
    private function setOhlcAvailability(StatisticsChartDto $dto, int $open_chat_id): void
    {
        $len = count($dto->date);
        $weekWindow = min(8, $len);
        $monthWindow = min(31, $len);

        $counts = $this->statisticsOhlcRepository->getOhlcCounts(
            $open_chat_id,
            $dto->date[$len - $weekWindow],
            $dto->date[$len - $monthWindow],
        );

        if ($counts['all_count'] === 0) {
            return;
        }

        $dto->ohlcAvailability = [
            'week' => $counts['week_count'] >= $weekWindow,
            'month' => $counts['month_count'] * 2 >= $monthWindow,
            'all' => true,
        ];
    }

    /**  
     *  @param string $startDate `Y-m-d`
     *  @return string[]
     */
    private function generateDateArray(string $startDate, string $endDate): array
    {
        $first = new \DateTime($startDate);
        $interval = $first->diff(new \DateTime($endDate))->days;

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
            $member = null;
            if ($memberStatsCurDate === $date) {
                $member = $memberStats[$curKeyMemberStats]['member'];
                $curKeyMemberStats++;
                $memberStatsCurDate = $getMemberStatsCurDate($curKeyMemberStats);
            }
            $dto->addValue($date, $member);
        }

        return $dto;
    }
}

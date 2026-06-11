<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Models\Repositories\RankingPosition\RankingPositionRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsPageRepositoryInterface;
use App\Services\Statistics\Dto\StatisticsChartDto;
use App\Services\Storage\FileStorageInterface;

class StatisticsChartArrayService
{
    /** 最新24時間タブのウィンドウ幅（RankingPositionHourChartArrayServiceと同じ） */
    private const HOUR_INTERVAL = 24;

    function __construct(
        private StatisticsPageRepositoryInterface $statisticsPageRepository,
        private StatisticsOhlcRepositoryInterface $statisticsOhlcRepository,
        private RankingPositionRepositoryInterface $rankingPositionRepository,
        private RankingPositionHourRepositoryInterface $rankingPositionHourRepository,
        private FileStorageInterface $fileStorage,
    ) {}

    /**
     * 日毎のメンバー数の統計を取得する
     *
     * @param int|null $category 部屋のカテゴリID（未掲載・その他はnull/0）
     * @param bool $withAvailability タブ・ボタン出し分け用の可用性メタデータを計算するか
     * @return array{ date: string, member: int }[] date: Y-m-d
     */
    function buildStatisticsChartArray(int $open_chat_id, int|null $category = null, bool $withAvailability = true): StatisticsChartDto|false
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

        if ($withAvailability) {
            $this->setOhlcAvailability($dto, $open_chat_id);
            $this->setPositionAvailability($dto, $open_chat_id, $category);
        }

        return $dto;
    }

    /**
     * 期間タブ×種別×カテゴリ毎のランキング順位データ有無と、
     * 最新24時間タブの毎時メンバー数データ有無を設定する
     *
     * フロントエンドはこれを基に「最新24時間」タブと
     * 「ランキングの順位を表示」の各ボタンを期間毎に出し分ける
     */
    private function setPositionAvailability(StatisticsChartDto $dto, int $open_chat_id, int|null $category): void
    {
        // カテゴリ未設定(その他/未掲載)の部屋はカテゴリ内ランキングを持たない
        $inCategory = ($category !== null && $category > 0) ? $category : -1;

        $len = count($dto->date);

        try {
            $counts = $this->rankingPositionRepository->getPositionCountsByPeriod(
                $open_chat_id,
                $inCategory,
                $dto->date[$len - min(8, $len)],
                $dto->date[$len - min(31, $len)],
            );
        } catch (\PDOException) {
            // DBファイル未作成の環境は「データ無し」として扱う
            return;
        }

        foreach (['week', 'month', 'all'] as $period) {
            $dto->positionAvailability[$period] = [
                'ranking_in' => $counts['ranking_in'][$period] > 0,
                'ranking_all' => $counts['ranking_all'][$period] > 0,
                'rising_in' => $counts['rising_in'][$period] > 0,
                'rising_all' => $counts['rising_all'][$period] > 0,
            ];
        }

        try {
            $updatedAt = $this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime');
            $hourCounts = $this->rankingPositionHourRepository->getHourPositionCounts(
                $open_chat_id,
                $inCategory,
                self::HOUR_INTERVAL,
                new \DateTime($updatedAt),
            );
        } catch (\Throwable) {
            // 毎時クロール未実行・DB未作成の環境は「データ無し」として扱う
            return;
        }

        $dto->hourAvailability = $hourCounts['member'] > 0;
        $dto->positionAvailability['hour'] = [
            'ranking_in' => $hourCounts['ranking_in'] > 0,
            'ranking_all' => $hourCounts['ranking_all'] > 0,
            'rising_in' => $hourCounts['rising_in'] > 0,
            'rising_all' => $hourCounts['rising_all'] > 0,
        ];
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

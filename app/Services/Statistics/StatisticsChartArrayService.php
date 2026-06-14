<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\Repositories\Statistics\StatisticsPageRepositoryInterface;
use App\Services\Statistics\Dto\StatisticsChartDto;

/**
 * 日毎のメンバー数系列（date/member）を組み立てる専用サービス。
 *
 * タブ・ボタン出し分け用の「可用性メタ」はここでは計算しない（ChartMetaBuilder に集約済み）。
 * 本サービスは折れ線/ローソク足/日次ビューが共通で必要とするメンバー数の日付軸つき系列だけを返す。
 */
class StatisticsChartArrayService
{
    function __construct(
        private StatisticsPageRepositoryInterface $statisticsPageRepository,
    ) {}

    /**
     * 日毎のメンバー数の統計を取得する。
     *
     * $from と $to を両方与えると「範囲モード」になり、メンバー取得と日付軸を
     * from〜to に限定する（フロントが見えている窓だけ取得するための土台）。
     * 範囲モードでない（片方でも null）ときは従来どおり全期間を返す。
     *
     * @param int|null $category 部屋のカテゴリID（呼び出し側 API の互換のため受け取るが系列生成には未使用）
     * @param ?string $from `Y-m-d` 範囲開始日（$to と併用時のみ有効）
     * @param ?string $to   `Y-m-d` 範囲終了日（$from と併用時のみ有効）
     */
    function buildStatisticsChartArray(
        int $open_chat_id,
        int|null $category = null,
        ?string $from = null,
        ?string $to = null,
    ): StatisticsChartDto|false {
        $useRange = $from !== null && $to !== null;

        $memberStats = $this->statisticsPageRepository->getDailyMemberStatsDateAsc(
            $open_chat_id,
            $useRange ? $from : null,
            $useRange ? $to : null,
        );

        if (!$memberStats) {
            return false;
        }

        // 範囲モードは渡された from/to をそのまま日付軸の境界にする（フロントが from を
        // 実データ開始日にクランプして送る前提）。全期間モードは実データの最古〜最新を使う。
        $startDate = $useRange ? $from : $memberStats[0]['date'];
        $endDate = $useRange ? $to : $memberStats[count($memberStats) - 1]['date'];

        $dto = new StatisticsChartDto($startDate, $endDate);

        $this->generateChartArray(
            $dto,
            $this->generateDateArray($dto->startDate, $dto->endDate),
            $memberStats
        );

        return $dto;
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

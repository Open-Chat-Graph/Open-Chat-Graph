<?php

declare(strict_types=1);

namespace App\Services\RankingPosition\Persistence;

use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Models\Repositories\RankingPosition\RankingPositionRepositoryInterface;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;

class RankingPositionDailyPersistence
{
    private string $date;

    function __construct(
        private RankingPositionHourRepositoryInterface $rankingPositionHourRepository,
        private RankingPositionRepositoryInterface $rankingPositionRepository,
    ) {
        $this->date = OpenChatServicesUtility::getCronModifiedStatsMemberDate();
    }

    function persistHourToDaily(): bool
    {
        if ($this->rankingPositionRepository->getLastDate() === $this->date) {
            addVerboseCronLog('日次ランキングデータの永続化はスキップ（本日実行済み: ' . $this->date . '）');
            return false;
        }

        $date = new \DateTime($this->date);

        $this->insert(
            $date,
            $this->rankingPositionHourRepository->getDaliyRanking(...),
            $this->rankingPositionRepository->insertDailyRankingPosition(...)
        );

        $this->insert(
            $date,
            $this->rankingPositionHourRepository->getDailyRising(...),
            $this->rankingPositionRepository->insertDailyRisingPosition(...)
        );

        $this->rankingPositionRepository->insertTotalCount(
            $this->rankingPositionHourRepository->getTotalCount($date)
        );

        addVerboseCronLog('日次ランキングデータの永続化が完了: ' . $this->date);
        return true;
    }

    private function insert(\DateTime $date, \Closure $getter, \Closure $inserter)
    {
        $inserter($getter($date), $this->date);
        $inserter($getter($date, true), $this->date);
    }
}

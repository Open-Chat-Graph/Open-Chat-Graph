<?php

declare(strict_types=1);

namespace App\Services\RankingPosition;

use App\Models\Repositories\OpenChatRepositoryInterface;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Cron\Enum\SyncOpenChatStateType;
use App\Services\RankingPosition\Persistence\RankingPositionDailyPersistence;
use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;

class RankingPositionDailyUpdater
{
    private string $date;

    function __construct(
        private RankingPositionDailyPersistence $rankingPositionDailyPersistence,
        private StatisticsRepositoryInterface $statisticsRepository,
        private RankingPositionHourRepositoryInterface $rankingPositionHourRepository,
        private OpenChatRepositoryInterface $openChatRepository,
        private SyncOpenChatStateRepositoryInterface $syncStateRepository,
    ) {
        $this->date = OpenChatServicesUtility::getCronModifiedStatsMemberDate();
    }

    function updateYesterdayDailyDb()
    {
        addVerboseCronLog('毎時ランキングデータを日次データに集約中');
        $this->rankingPositionDailyPersistence->persistHourToDaily();
        $this->persistMemberStatsFromRankingPositionDb();
        addVerboseCronLog('毎時→日次データ集約完了');
    }

    private function persistMemberStatsFromRankingPositionDb(): void
    {
        // 実行済みチェック：同じ日付で既に実行済みならスキップ
        if ($this->syncStateRepository->getString(SyncOpenChatStateType::persistMemberStatsLastDate) === $this->date) {
            addVerboseCronLog('毎時人数データの永続化はスキップ（本日実行済み: ' . $this->date . '）');
            return;
        }

        $data = $this->rankingPositionHourRepository->getDailyMemberStats(new \DateTime($this->date));

        $ocDbIdArray = $this->openChatRepository->getOpenChatIdAll();

        $filteredData = array_filter($data, fn($stats) => in_array($stats['open_chat_id'], $ocDbIdArray));
        unset($ocDbIdArray);

        $this->statisticsRepository->insertMember($filteredData);

        // 実行日を保存
        $this->syncStateRepository->setString(SyncOpenChatStateType::persistMemberStatsLastDate, $this->date);
        addVerboseCronLog('毎時人数データの永続化が完了: ' . $this->date);
    }
}

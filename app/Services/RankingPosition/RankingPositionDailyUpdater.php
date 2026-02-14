<?php

declare(strict_types=1);

namespace App\Services\RankingPosition;

use App\Models\Repositories\OpenChatRepositoryInterface;
use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Models\Repositories\RankingPosition\RankingPositionOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Cron\Enum\SyncOpenChatStateType;
use App\Services\Cron\Utility\CronUtility;
use App\Services\OpenChat\Enum\RankingType;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Services\RankingPosition\Persistence\RankingPositionDailyPersistence;

class RankingPositionDailyUpdater
{
    private string $date;

    function __construct(
        private RankingPositionDailyPersistence $rankingPositionDailyPersistence,
        private StatisticsRepositoryInterface $statisticsRepository,
        private StatisticsOhlcRepositoryInterface $statisticsOhlcRepository,
        private RankingPositionOhlcRepositoryInterface $rankingPositionOhlcRepository,
        private RankingPositionHourRepositoryInterface $rankingPositionHourRepository,
        private OpenChatRepositoryInterface $openChatRepository,
        private SyncOpenChatStateRepositoryInterface $syncStateRepository,
    ) {
        $this->date = OpenChatServicesUtility::getCronModifiedStatsMemberDate();
    }

    function updateYesterdayDailyDb()
    {
        CronUtility::addVerboseCronLog('毎時ランキングデータを日次データに集約中');
        $this->rankingPositionDailyPersistence->persistHourToDaily();
        $this->persistMemberStatsFromRankingPositionDb();
        CronUtility::addVerboseCronLog('毎時→日次データ集約完了');
    }

    private function persistMemberStatsFromRankingPositionDb(): void
    {
        // 実行済みチェック：同じ日付で既に実行済みならスキップ
        if ($this->syncStateRepository->getString(SyncOpenChatStateType::persistMemberStatsLastDate) === $this->date) {
            CronUtility::addVerboseCronLog('毎時人数データの永続化はスキップ（本日実行済み: ' . $this->date . '）');
            return;
        }

        $data = $this->rankingPositionHourRepository->getDailyMemberStats(new \DateTime($this->date));

        $ocDbIdArray = $this->openChatRepository->getOpenChatIdAll();

        $filteredData = array_filter($data, fn($stats) => in_array($stats['open_chat_id'], $ocDbIdArray));
        unset($data);

        $this->statisticsRepository->insertMember($filteredData);
        unset($filteredData);

        // OHLCデータの永続化
        $ohlcData = $this->rankingPositionHourRepository->getDailyMemberOhlc(new \DateTime($this->date));
        $filteredOhlcData = array_filter($ohlcData, fn($stats) => in_array($stats['open_chat_id'], $ocDbIdArray));
        unset($ohlcData, $ocDbIdArray);

        $this->statisticsOhlcRepository->insertOhlc($filteredOhlcData);
        CronUtility::addVerboseCronLog('メンバーOHLCデータの永続化が完了: ' . $this->date);

        // ランキング順位OHLCの永続化
        $this->persistRankingPositionOhlc();

        // 実行日を保存
        $this->syncStateRepository->setString(SyncOpenChatStateType::persistMemberStatsLastDate, $this->date);
        CronUtility::addVerboseCronLog('毎時人数データの永続化が完了: ' . $this->date);
    }

    private function persistRankingPositionOhlc(): void
    {
        $date = new \DateTime($this->date);

        $rankingOhlc = $this->rankingPositionHourRepository->getDailyPositionOhlc(RankingType::Ranking, $date);
        $risingOhlc = $this->rankingPositionHourRepository->getDailyPositionOhlc(RankingType::Rising, $date);

        $allOhlc = array_merge(
            array_map(fn($r) => [...$r, 'type' => RankingType::Ranking->value], $rankingOhlc),
            array_map(fn($r) => [...$r, 'type' => RankingType::Rising->value], $risingOhlc)
        );
        unset($rankingOhlc, $risingOhlc);

        $this->rankingPositionOhlcRepository->insertOhlc($allOhlc);
        CronUtility::addVerboseCronLog('ランキング順位OHLCデータの永続化が完了: ' . $this->date);
    }
}

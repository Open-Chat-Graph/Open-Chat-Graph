<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\AppConfig;
use App\Services\StaticData\StaticDataGenerator;
use App\Models\Repositories\Statistics\StatisticsRankingUpdaterRepositoryInterface;
use App\Models\Repositories\OpenChatListRepositoryInterface;

class UpdateDailyRankingService
{
    function __construct(
        private StaticDataGenerator $staticDataGenerator,
        private StatisticsRankingUpdaterRepositoryInterface $rankingUpdater,
        private OpenChatListRepositoryInterface $openChatListRepository,
    ) {
    }

    /**
     * @param string $date Y-m-d
     */
    function update(string $date)
    {
        $this->rankingUpdater->updateCreateDailyRankingTable($date);
        $this->rankingUpdater->updateCreatePastWeekRankingTable($date);
        $this->updateStaticData($date);
    }

    private function updateStaticData(string $date)
    {
        safeFileRewrite(AppConfig::getStorageFilePath('dailyCronUpdatedAtDate'), $date);
        $this->staticDataGenerator->updateStaticData();
    }
}

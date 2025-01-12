<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\AppConfig;
use App\Models\Repositories\RankingPosition\HourMemberRankingUpdaterRepositoryInterface;
use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;
use App\Services\Recommend\StaticData\RecommendStaticDataGenerator;
use App\Services\StaticData\StaticDataGenerator;

class UpdateHourlyMemberRankingService
{
    function __construct(
        private StaticDataGenerator $staticDataGenerator,
        private RecommendStaticDataGenerator $recommendStaticDataGenerator,
        private HourMemberRankingUpdaterRepositoryInterface $hourMemberRankingUpdaterRepository,
        private RankingPositionHourRepositoryInterface $rankingPositionHourRepository,
        private StatisticsRepositoryInterface $statisticsRepository,
    ) {}

    function update()
    {
        $time = $this->rankingPositionHourRepository->getLastHour();
        if (!$time) return;

        addVerboseCronLog(__METHOD__ . ' Start ' . 'HourMemberRankingUpdaterRepositoryInterface::updateHourRankingTable');
        $this->hourMemberRankingUpdaterRepository->updateHourRankingTable(
            new \DateTime($time),
            $this->getCachedFilters($time)
        );
        addVerboseCronLog(__METHOD__ . ' Done ' . 'HourMemberRankingUpdaterRepositoryInterface::updateHourRankingTable');

        $this->updateStaticData($time);
        $this->saveNextFiltersCache($time);
    }

    private function getCachedFilters(string $time)
    {
        $filters = getUnserializedFile(AppConfig::getStorageFilePath('openChatHourFilterId'));
        return $filters
            ? $filters
            : $this->statisticsRepository->getHourMemberChangeWithinLastWeekArray((new \DateTime($time))->format('Y-m-d'));
    }

    private function saveNextFiltersCache(string $time)
    {
        addVerboseCronLog(__METHOD__ . ' Start ' . 'StatisticsRepositoryInterface::getHourMemberChangeWithinLastWeekArray');
        saveSerializedFile(
            AppConfig::getStorageFilePath('openChatHourFilterId'),
            $this->statisticsRepository->getHourMemberChangeWithinLastWeekArray((new \DateTime($time))->format('Y-m-d')),
        );
        addVerboseCronLog(__METHOD__ . ' Done ' . 'StatisticsRepositoryInterface::getHourMemberChangeWithinLastWeekArray');
    }

    private function updateStaticData(string $time)
    {
        safeFileRewrite(AppConfig::getStorageFilePath('hourlyCronUpdatedAtDatetime'), $time);

        addVerboseCronLog(__METHOD__ . ' Start ' . 'StaticDataGenerator::updateStaticData');
        $this->staticDataGenerator->updateStaticData();
        addVerboseCronLog(__METHOD__ . ' Done ' . 'StaticDataGenerator::updateStaticData');

        addVerboseCronLog(__METHOD__ . ' Start ' . 'RecommendStaticDataGenerator::updateStaticData');
        $this->recommendStaticDataGenerator->updateStaticData();
        addVerboseCronLog(__METHOD__ . ' Done ' . 'RecommendStaticDataGenerator::updateStaticData');
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\AppConfig;
use App\Models\Repositories\OpenChatListRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsRankingUpdaterRepositoryInterface;
use App\Services\Cron\Utility\CronUtility;
use App\Services\StaticData\StaticDataGenerator;

class UpdateDailyRankingService
{
    function __construct(
        private StaticDataGenerator $staticDataGenerator,
        private StatisticsRankingUpdaterRepositoryInterface $rankingUpdater,
        private OpenChatListRepositoryInterface $openChatListRepository,
    ) {}

    /**
     * @param string $date Y-m-d
     */
    function update(string $date)
    {
        CronUtility::addVerboseCronLog('日次ランキング更新処理を実行中（対象日: ' . $date . '）');
        $this->rankingUpdater->updateCreateDailyRankingTable($date);
        CronUtility::addVerboseCronLog('日次ランキング更新処理完了');

        CronUtility::addVerboseCronLog('過去１週間ランキング更新処理を実行中（対象日: ' . $date . '）');
        $this->rankingUpdater->updateCreatePastWeekRankingTable($date);
        CronUtility::addVerboseCronLog('過去１週間ランキング更新処理完了');

        CronUtility::addVerboseCronLog('ランキング静的データを生成中（対象日: ' . $date . '）');
        $this->updateStaticData($date);
        CronUtility::addVerboseCronLog('ランキング静的データ生成処理完了');
    }

    private function updateStaticData(string $date)
    {
        safeFileRewrite(AppConfig::getStorageFilePath('dailyCronUpdatedAtDate'), $date);
        $this->staticDataGenerator->updateStaticData();
    }
}

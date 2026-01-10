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
    ) {}

    /**
     * @param string $date Y-m-d
     */
    function update(string $date)
    {
        addVerboseCronLog('日次ランキング更新処理を実行中（対象日: ' . $date . '）');
        $this->rankingUpdater->updateCreateDailyRankingTable($date);
        addVerboseCronLog('日次ランキング更新処理完了');

        addVerboseCronLog('過去１週間ランキング更新処理を実行中（対象日: ' . $date . '）');
        $this->rankingUpdater->updateCreatePastWeekRankingTable($date);
        addVerboseCronLog('過去１週間ランキング更新処理完了');

        addVerboseCronLog('ランキング静的データを生成中（対象日: ' . $date . '）');
        $this->updateStaticData($date);
        addVerboseCronLog('ランキング静的データ生成処理完了');
    }

    private function updateStaticData(string $date)
    {
        safeFileRewrite(AppConfig::getStorageFilePath('dailyCronUpdatedAtDate'), $date);
        $this->staticDataGenerator->updateStaticData();
    }
}

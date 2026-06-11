<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Repositories\MemberChangeFilterCacheRepositoryInterface;
use App\Models\Repositories\RankingPosition\HourMemberRankingUpdaterRepositoryInterface;
use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Services\Cron\Enum\BatchScript;
use App\Services\Cron\Utility\BatchScriptLauncher;
use App\Services\Cron\Utility\CronUtility;
use App\Services\StaticData\StaticDataGenerator;
use App\Services\Storage\FileStorageInterface;

class UpdateHourlyMemberRankingService
{
    function __construct(
        private StaticDataGenerator $staticDataGenerator,
        private HourMemberRankingUpdaterRepositoryInterface $hourMemberRankingUpdaterRepository,
        private RankingPositionHourRepositoryInterface $rankingPositionHourRepository,
        private MemberChangeFilterCacheRepositoryInterface $memberChangeFilterCacheRepository,
        private FileStorageInterface $fileStorage,
        private BatchScriptLauncher $batchScriptLauncher,
    ) {}

    function update()
    {
        $time = $this->rankingPositionHourRepository->getLastHour();
        if (!$time) return;

        CronUtility::addVerboseCronLog('毎時メンバーランキングテーブルを更新中');
        $this->hourMemberRankingUpdaterRepository->updateHourRankingTable(
            new \DateTime($time),
            $this->getCachedFilters($time)
        );
        CronUtility::addVerboseCronLog('毎時メンバーランキングテーブル更新完了');

        $this->updateStaticData($time);
    }

    private function getCachedFilters(string $time)
    {
        // 変動がある部屋（キャッシュ） + 新規部屋（リアルタイム）
        return $this->memberChangeFilterCacheRepository->getForHourly(
            (new \DateTime($time))->format('Y-m-d')
        );
    }

    private function updateStaticData(string $time)
    {
        $this->fileStorage->safeFileRewrite('@hourlyCronUpdatedAtDatetime', $time);

        CronUtility::addVerboseCronLog('ランキング静的データを生成中');
        $this->staticDataGenerator->updateStaticData();
        CronUtility::addVerboseCronLog('ランキング静的データ生成完了');

        // おすすめ静的データ生成とCDNキャッシュ削除をバックグラウンドで実行
        $this->executeRecommendStaticDataGeneratorInBackground();
    }

    private function executeRecommendStaticDataGeneratorInBackground()
    {
        $this->batchScriptLauncher->launchInBackground(BatchScript::updateRecommendStaticData, \Shared\MimimalCmsConfig::$urlRoot);
        CronUtility::addVerboseCronLog('おすすめ静的データ生成をバックグラウンドで開始');
    }
}

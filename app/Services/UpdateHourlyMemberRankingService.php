<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\AppConfig;
use App\Models\Repositories\MemberChangeFilterCacheRepositoryInterface;
use App\Models\Repositories\RankingPosition\HourMemberRankingUpdaterRepositoryInterface;
use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Services\Recommend\StaticData\RecommendStaticDataGenerator;
use App\Services\StaticData\StaticDataGenerator;

class UpdateHourlyMemberRankingService
{
    function __construct(
        private StaticDataGenerator $staticDataGenerator,
        private RecommendStaticDataGenerator $recommendStaticDataGenerator,
        private HourMemberRankingUpdaterRepositoryInterface $hourMemberRankingUpdaterRepository,
        private RankingPositionHourRepositoryInterface $rankingPositionHourRepository,
        private MemberChangeFilterCacheRepositoryInterface $memberChangeFilterCacheRepository,
    ) {}

    function update()
    {
        $time = $this->rankingPositionHourRepository->getLastHour();
        if (!$time) return;

        addVerboseCronLog('毎時メンバーランキングテーブルを更新中');
        $this->hourMemberRankingUpdaterRepository->updateHourRankingTable(
            new \DateTime($time),
            $this->getCachedFilters($time)
        );
        addVerboseCronLog('毎時メンバーランキングテーブル更新完了');

        $this->updateStaticData($time);
    }

    private function getCachedFilters(string $time)
    {
        $date = (new \DateTime($time))->format('Y-m-d');

        // 変動がある部屋（キャッシュ） + 新規部屋（リアルタイム）
        return $this->memberChangeFilterCacheRepository->getForHourly($date);
    }

    private function updateStaticData(string $time)
    {
        safeFileRewrite(AppConfig::getStorageFilePath('hourlyCronUpdatedAtDatetime'), $time);

        addVerboseCronLog('ランキング静的データを生成中');
        $this->staticDataGenerator->updateStaticData();
        addVerboseCronLog('ランキング静的データ生成完了');

        addVerboseCronLog('おすすめ静的データを生成中');
        $this->recommendStaticDataGenerator->updateStaticData();
        addVerboseCronLog('おすすめ静的データ生成完了');
    }
}

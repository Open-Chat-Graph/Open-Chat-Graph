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

    function update(bool $saveNextFiltersCache = true)
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

        if ($saveNextFiltersCache)
            $this->saveNextFiltersCache($time);
    }

    /**
     * dailyTask後にフィルターキャッシュを保存する
     *
     * - 日付管理により、同じ日に複数回実行されても1回だけデータ取得
     * - getMemberChangeWithinLastWeekCacheArray()は全statisticsテーブルをスキャンする重い処理のため、1日1回に制限
     * - dailyTask実行時に既にこのデータを取得しているが、日付チェックによりスキップされるため実質的に重複実行は発生しない
     */
    function saveFiltersCacheAfterDailyTask(): void
    {
        $time = $this->rankingPositionHourRepository->getLastHour();
        if (!$time) return;

        $date = (new \DateTime($time))->format('Y-m-d');

        // キャッシュの日付ファイルをチェック
        $cacheDateFilePath = AppConfig::getStorageFilePath('openChatHourFilterIdDate');
        $cachedDate = @file_get_contents($cacheDateFilePath);

        // すでに今日のキャッシュがある場合はスキップ
        // dailyTaskが同じ日に複数回実行されても、データ取得は1回のみ
        if ($cachedDate === $date) {
            addCronLog(__METHOD__ . ': Skip - Cache already updated today');
            return;
        }

        // キャッシュを更新（変動がある部屋 + 新規部屋を含む全データ）
        addVerboseCronLog(__METHOD__ . ' Start ' . 'StatisticsRepositoryInterface::getMemberChangeWithinLastWeekCacheArray');
        $filterIds = $this->statisticsRepository->getMemberChangeWithinLastWeekCacheArray($date);

        // フィルターIDを保存
        saveSerializedFile(
            AppConfig::getStorageFilePath('openChatHourFilterId'),
            $filterIds
        );

        // 日付を保存
        safeFileRewrite($cacheDateFilePath, $date);

        addVerboseCronLog(__METHOD__ . ' Done ' . 'StatisticsRepositoryInterface::getMemberChangeWithinLastWeekCacheArray');
    }

    private function getCachedFilters(string $time)
    {
        // キャッシュから「変動がある部屋」を取得
        $cachedFilters = getUnserializedFile(AppConfig::getStorageFilePath('openChatHourFilterId'));

        // キャッシュがない場合は全て取得
        if (!$cachedFilters) {
            return $this->statisticsRepository->getHourMemberChangeWithinLastWeekArray((new \DateTime($time))->format('Y-m-d'));
        }

        // 「レコード8以下の新規部屋」を毎回取得してマージ（約5秒）
        // これにより新規ルームのリアルタイム性を確保
        $newRooms = $this->statisticsRepository->getNewRoomsWithLessThan8Records();

        return array_unique(array_merge($cachedFilters, $newRooms));
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

<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\AppConfig;
use App\Models\Repositories\OpenChatDataForUpdaterWithCacheRepository;
use App\Models\Repositories\OpenChatRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;
use App\Services\OpenChat\OpenChatDailyCrawling;
use App\Services\OpenChat\SubCategory\OpenChatSubCategorySynchronizer;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Services\RankingPosition\RankingPositionDailyUpdater;

class DailyUpdateCronService
{
    private string $date;

    /**
     * dailyTask時にgetMemberChangeWithinLastWeekCacheArrayで取得したデータを保存
     * saveFiltersCacheAfterDailyTaskで再利用するため（重複クエリ防止）
     *
     * @var int[]|null
     */
    private ?array $cachedMemberChangeIdArray = null;

    function __construct(
        private RankingPositionDailyUpdater $rankingPositionDailyUpdater,
        private OpenChatDailyCrawling $openChatDailyCrawling,
        private OpenChatRepositoryInterface $openChatRepository,
        private StatisticsRepositoryInterface $statisticsRepository,
        private UpdateDailyRankingService $updateRankingService,
        private OpenChatSubCategorySynchronizer $openChatSubCategorySynchronizer,
    ) {
        $this->date = OpenChatServicesUtility::getCronModifiedStatsMemberDate();
    }

    /**
     * @return int[]
     */
    function getTargetOpenChatIdArray(): array
    {
        addVerboseCronLog('DailyUpdateCronService::getTargetOpenChatIdArray Start');
        $ocDbIdArray = $this->openChatRepository->getOpenChatIdAllByCreatedAtDate($this->date);
        addVerboseCronLog('Total OpenChat count for date ' . $this->date . ': ' . count($ocDbIdArray));

        addVerboseCronLog('DailyUpdateCronService::getTargetOpenChatIdArray Start getOpenChatIdArrayByDate');
        $statsDbIdArray = $this->statisticsRepository->getOpenChatIdArrayByDate($this->date);
        addVerboseCronLog('DailyUpdateCronService::getTargetOpenChatIdArray End getOpenChatIdArrayByDate');

        $filteredIdArray = array_diff($ocDbIdArray, $statsDbIdArray);

        // 重いクエリを1回だけ実行し、結果をプロパティに保存
        addVerboseCronLog('DailyUpdateCronService::getTargetOpenChatIdArray Start getMemberChangeWithinLastWeekCacheArray');
        $this->cachedMemberChangeIdArray = $this->statisticsRepository->getMemberChangeWithinLastWeekCacheArray($this->date);
        addVerboseCronLog('DailyUpdateCronService::getTargetOpenChatIdArray End getMemberChangeWithinLastWeekCacheArray');

        return array_filter($filteredIdArray, fn (int $id) => in_array($id, $this->cachedMemberChangeIdArray));
    }

    /**
     * getTargetOpenChatIdArray()で取得したフィルターキャッシュデータを返す
     * saveFiltersCacheAfterDailyTaskで再利用するため
     *
     * @return int[]|null
     */
    function getCachedMemberChangeIdArray(): ?array
    {
        return $this->cachedMemberChangeIdArray;
    }

    function update(?\Closure $crawlingEndFlag = null): void
    {
        addVerboseCronLog('DailyUpdateCronService start for date: ' . $this->date);
        
        $this->rankingPositionDailyUpdater->updateYesterdayDailyDb();

        $outOfRankId = $this->getTargetOpenChatIdArray();

        addCronLog('openChatCrawling start: ' . count($outOfRankId));

        // 開発環境の場合、更新制限をかける
        $isDevelopment = AppConfig::$isDevlopment ?? false;
        if ($isDevelopment) {
            $limit = AppConfig::$developmentEnvUpdateLimit['DailyUpdateCronService'] ?? 1;
            $outOfRankIdCount = count($outOfRankId);
            $outOfRankId = array_slice($outOfRankId, 0, $limit);
            addCronLog("Development environment. Update limit: {$limit} / {$outOfRankIdCount}");
        }

        // 並列処理でクローリング実行
        addCronLog('Using parallel crawling');
        $result = $this->openChatDailyCrawling->crawling($outOfRankId);

        addCronLog('openChatCrawling done: ' . $result);
        unset($outOfRankId);
        OpenChatDataForUpdaterWithCacheRepository::clearCache();

        if ($crawlingEndFlag)
            $crawlingEndFlag();

        addCronLog('syncSubCategoriesAll start');
        $categoryResult = $this->openChatSubCategorySynchronizer->syncSubCategoriesAll();
        addCronLog('syncSubCategoriesAll done: ' . count($categoryResult));

        $this->updateRankingService->update($this->date);
    }
}

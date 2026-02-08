<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\AppConfig;
use App\Exceptions\ApplicationException;
use App\Models\Repositories\MemberChangeFilterCacheRepositoryInterface;
use App\Models\Repositories\OpenChatDataForUpdaterWithCacheRepository;
use App\Models\Repositories\OpenChatRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;
use App\Services\Cron\Utility\CronUtility;
use App\Services\OpenChat\OpenChatDailyCrawling;
use App\Services\OpenChat\SubCategory\OpenChatSubCategorySynchronizer;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Services\RankingPosition\RankingPositionDailyUpdater;

class DailyUpdateCronService
{
    private string $date;

    function __construct(
        private RankingPositionDailyUpdater $rankingPositionDailyUpdater,
        private OpenChatDailyCrawling $openChatDailyCrawling,
        private OpenChatRepositoryInterface $openChatRepository,
        private StatisticsRepositoryInterface $statisticsRepository,
        private UpdateDailyRankingService $updateRankingService,
        private OpenChatSubCategorySynchronizer $openChatSubCategorySynchronizer,
        private MemberChangeFilterCacheRepositoryInterface $memberChangeFilterCacheRepository,
    ) {
        $this->date = OpenChatServicesUtility::getCronModifiedStatsMemberDate();
    }

    /**
     * @return int[]
     */
    function getTargetOpenChatIdArray(): array
    {
        CronUtility::addVerboseCronLog('クローリング対象のオープンチャットを抽出中');
        $ocDbIdArray = $this->openChatRepository->getOpenChatIdAllByCreatedAtDate($this->date);
        CronUtility::addVerboseCronLog('登録オープンチャット数（' . $this->date . '）: ' . count($ocDbIdArray));

        CronUtility::addVerboseCronLog('統計データ未登録のオープンチャットを取得中');
        $statsDbIdArray = $this->statisticsRepository->getOpenChatIdArrayByDate($this->date);
        CronUtility::addVerboseCronLog('統計データ未登録のオープンチャット取得完了');

        $filteredIdArray = array_diff($ocDbIdArray, $statsDbIdArray);

        // キャッシュから取得、またはDBから取得して自動でキャッシュ保存
        $memberChangeIdArray = $this->memberChangeFilterCacheRepository->getForDaily($this->date);

        return array_values(array_filter($filteredIdArray, fn(int $id) => in_array($id, $memberChangeIdArray)));
    }

    function update(?\Closure $crawlingEndFlag = null): void
    {
        $title = isDailyUpdateTime() ? '開始' : '再開';

        CronUtility::addVerboseCronLog('日次データ更新を' . $title . '（対象日: ' . $this->date . '）');

        $this->rankingPositionDailyUpdater->updateYesterdayDailyDb();

        $outOfRankId = $this->getTargetOpenChatIdArray();

        CronUtility::addCronLog('ランキング外オープンチャットのクローリング' . $title . ': 残り' . count($outOfRankId) . '件');

        // 開発環境・ステージング環境の場合、更新制限をかける
        $isDevelopment = AppConfig::$isDevlopment ?? false;
        $isStaging = AppConfig::$isStaging ?? false;
        if ($isDevelopment || $isStaging) {
            $limit = AppConfig::$developmentEnvUpdateLimit['DailyUpdateCronService'] ?? 1;
            $outOfRankIdCount = count($outOfRankId);
            $outOfRankId = array_slice($outOfRankId, 0, $limit);
            CronUtility::addCronLog("Development environment. Update limit: {$limit} / {$outOfRankIdCount}");
        }

        try {
            $result = $this->openChatDailyCrawling->crawling($outOfRankId);
        } catch (\Throwable $e) {
            $result = $e->getMessage();
            CronUtility::addCronLog("ランキング外オープンチャットのクローリングが中断されました: {$result} / " . count($outOfRankId) . "件中");
            throw new ApplicationException('強制終了しました', ApplicationException::DAILY_UPDATE_EXCEPTION_ERROR_CODE);
        }

        CronUtility::addCronLog('ランキング外オープンチャットのクローリング完了: ' . $result . '件');
        unset($outOfRankId);
        OpenChatDataForUpdaterWithCacheRepository::clearCache();

        if ($crawlingEndFlag)
            $crawlingEndFlag();

        CronUtility::addCronLog('サブカテゴリ同期を開始');
        $categoryResult = $this->openChatSubCategorySynchronizer->syncSubCategoriesAll();
        CronUtility::addCronLog('サブカテゴリ同期完了: ' . count($categoryResult) . '件');

        CronUtility::addCronLog('日次ランキング更新を開始');
        $this->updateRankingService->update($this->date);
        CronUtility::addCronLog('日次ランキング更新完了');
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\AppConfig;
use App\Exceptions\ApplicationException;
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
        addVerboseCronLog('クローリング対象のオープンチャットを抽出中');
        $ocDbIdArray = $this->openChatRepository->getOpenChatIdAllByCreatedAtDate($this->date);
        addVerboseCronLog('登録オープンチャット数（' . $this->date . '）: ' . count($ocDbIdArray));

        addVerboseCronLog('統計データ未登録のオープンチャットを取得中');
        $statsDbIdArray = $this->statisticsRepository->getOpenChatIdArrayByDate($this->date);
        addVerboseCronLog('統計データ未登録のオープンチャット取得完了');

        $filteredIdArray = array_diff($ocDbIdArray, $statsDbIdArray);

        // 重いクエリを1回だけ実行し、結果をプロパティに保存
        addVerboseCronLog('メンバー数変動ありのオープンチャットを抽出中');
        $this->cachedMemberChangeIdArray = $this->statisticsRepository->getMemberChangeWithinLastWeekCacheArray($this->date);
        addVerboseCronLog('メンバー数変動ありのオープンチャット抽出完了');

        return array_filter($filteredIdArray, fn(int $id) => in_array($id, $this->cachedMemberChangeIdArray));
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
        $title = isDailyUpdateTime() ? '開始' : '再開';

        addVerboseCronLog('日次データ更新を' . $title . '（対象日: ' . $this->date . '）');

        $this->rankingPositionDailyUpdater->updateYesterdayDailyDb();

        $outOfRankId = $this->getTargetOpenChatIdArray();

        addCronLog('ランキング外オープンチャットのクローリング' . $title . ': 残り' . count($outOfRankId) . '件');

        // 開発環境の場合、更新制限をかける
        $isDevelopment = AppConfig::$isDevlopment ?? false;
        if ($isDevelopment) {
            $limit = AppConfig::$developmentEnvUpdateLimit['DailyUpdateCronService'] ?? 1;
            $outOfRankIdCount = count($outOfRankId);
            $outOfRankId = array_slice($outOfRankId, 0, $limit);
            addCronLog("Development environment. Update limit: {$limit} / {$outOfRankIdCount}");
        }

        try {
            $result = $this->openChatDailyCrawling->crawling($outOfRankId);
        } catch (\Throwable $e) {
            $result = $e->getMessage();
            addCronLog("ランキング外オープンチャットのクローリングが中断されました: {$result} / " . count($outOfRankId) . "件中");
            throw new ApplicationException('強制終了しました', AppConfig::DAILY_UPDATE_EXCEPTION_ERROR_CODE);
        }

        addCronLog('ランキング外オープンチャットのクローリング完了: ' . $result . '件');
        unset($outOfRankId);
        OpenChatDataForUpdaterWithCacheRepository::clearCache();

        if ($crawlingEndFlag)
            $crawlingEndFlag();

        addCronLog('サブカテゴリ同期を開始');
        $categoryResult = $this->openChatSubCategorySynchronizer->syncSubCategoriesAll();
        addCronLog('サブカテゴリ同期完了: ' . count($categoryResult) . '件');

        $this->updateRankingService->update($this->date);
    }
}

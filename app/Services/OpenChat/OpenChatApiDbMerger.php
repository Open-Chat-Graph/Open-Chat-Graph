<?php

declare(strict_types=1);

namespace App\Services\OpenChat;

use App\Config\AppConfig;
use App\Exceptions\ApplicationException;
use App\Models\Repositories\Log\LogRepositoryInterface;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Cron\Enum\SyncOpenChatStateType;
use App\Services\Cron\Utility\CronUtility;
use App\Services\OpenChat\Crawler\OpenChatApiRankingDownloader;
use App\Services\OpenChat\Crawler\OpenChatApiRankingDownloaderProcess;
use App\Services\OpenChat\Crawler\OpenChatApiRisingDownloaderProcess;
use App\Services\OpenChat\Dto\OpenChatApiDtoFactory;
use App\Services\OpenChat\Dto\OpenChatDto;
use App\Services\OpenChat\Updater\Process\OpenChatApiDbMergerProcess;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Services\RankingPosition\Store\AbstractRankingPositionStore;
use App\Services\RankingPosition\Store\RankingPositionStore;
use App\Services\RankingPosition\Store\RisingPositionStore;
use Shared\MimimalCmsConfig;

class OpenChatApiDbMerger
{
    /** URL取得の遅延警告閾値（秒） */
    private const URL_FETCH_SLOW_THRESHOLD_SECONDS = 10;

    private OpenChatApiRankingDownloader $rankingDownloader;
    private OpenChatApiRankingDownloader $risingDownloader;

    function __construct(
        private OpenChatApiDtoFactory $openChatApiDtoFactory,
        private OpenChatApiDbMergerProcess $openChatApiDbMergerProcess,
        private LogRepositoryInterface $logRepository,
        private RankingPositionStore $rankingStore,
        private RisingPositionStore $risingStore,
        private SyncOpenChatStateRepositoryInterface $syncOpenChatStateRepository,
        OpenChatApiRankingDownloaderProcess $openChatApiRankingDownloaderProcess,
        OpenChatApiRisingDownloaderProcess $openChatApiRisingDownloaderProcess,
    ) {
        $this->rankingDownloader = app(
            OpenChatApiRankingDownloader::class,
            ['openChatApiRankingDownloaderProcess' => $openChatApiRankingDownloaderProcess]
        );

        $this->risingDownloader = app(
            OpenChatApiRankingDownloader::class,
            ['openChatApiRankingDownloaderProcess' => $openChatApiRisingDownloaderProcess]
        );
    }

    /**
     * カテゴリラベルを取得
     */
    private function getCategoryLabel(string $category, AbstractRankingPositionStore $positionStore): string
    {
        $categoryName = array_flip(AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot])[$category] ?? 'Unknown';
        $typeLabel = str_contains(getClassSimpleName($positionStore), 'Rising') ? '急上昇' : 'ランキング';
        return "{$categoryName}の{$typeLabel}";
    }

    /**
     * APIから全ランキングデータを取得してDBマージ処理を行う
     * 
     * @return array{ count: int, category: string, dateTime: \DateTime }[] 取得済件数とカテゴリ
     * @throws \RuntimeException
     */
    function fetchOpenChatApiRankingAll(): array
    {
        $this->setKillFlagFalse();
        $startTime = microtime(true);
        CronUtility::addVerboseCronLog("LINE公式APIからランキングデータを取得開始");

        try {
            $result1 = $this->fetchOpenChatApiRankingAllProcess($this->risingStore, $this->risingDownloader);
            $result2 = $this->fetchOpenChatApiRankingAllProcess($this->rankingStore, $this->rankingDownloader);
            return [...$result1, ...$result2];
        } catch (\RuntimeException $e) {
            $this->logRepository->logUpdateOpenChatError(0, $e->__toString());
            throw $e;
        } finally {
            CronUtility::addVerboseCronLog("LINE公式APIからランキングデータを取得完了（" . formatElapsedTime($startTime) . "）");
        }
    }

    /**
     * APIから全ランキングデータを取得してDBマージ処理を行う
     * 
     * @return array{ count: int, category: string, dateTime: \DateTime }[] 取得済件数とカテゴリ
     * @throws \RuntimeException
     */
    private function fetchOpenChatApiRankingAllProcess(
        AbstractRankingPositionStore $positionStore,
        OpenChatApiRankingDownloader $downloader
    ): array {
        // API OC一件ずつの処理
        $processCallback = function (OpenChatDto $apiDto) use ($positionStore): ?string {
            $positionStore->addApiDto($apiDto);
            return $this->openChatApiDbMergerProcess->validateAndMapToOpenChatDtoCallback($apiDto);
        };

        // API URL一件ずつの処理時間計測用
        $urlCount = 0;
        $currentCategory = null;
        $lastCallbackTime = null;

        // API URL一件ずつの処理
        $callback = function (array $apiData) use ($processCallback, &$urlCount, &$currentCategory, &$startTimes, &$lastCallbackTime): void {
            $this->checkKillFlag();

            $now = microtime(true);
            $urlCount++;

            // 前回のコールバックからの経過時間をチェック
            if ($lastCallbackTime !== null) {
                $sinceLastCallback = $now - $lastCallbackTime;
                if ($sinceLastCallback >= self::URL_FETCH_SLOW_THRESHOLD_SECONDS) {
                    $categoryElapsed = isset($startTimes[$currentCategory])
                        ? formatElapsedTime($startTimes[$currentCategory])
                        : '不明';
                    $sinceLastFormatted = round($sinceLastCallback, 1);
                    CronUtility::addCronLog("[警告] URL1件の取得に{$sinceLastFormatted}秒: {$urlCount}件目（カテゴリ開始から{$categoryElapsed}経過）");
                }
            }

            $lastCallbackTime = $now;

            $errors = $this->openChatApiDtoFactory->validateAndMapToOpenChatDto($apiData, $processCallback);

            foreach ($errors as $error) {
                $this->logRepository->logUpdateOpenChatError(0, $error);
            }
        };

        /** @var array<string, string|float> $startTimes APIカテゴリごとの処理 */
        $startTimes = [];

        $callbackByCategoryBefore = function (string $category) use (
            $positionStore,
            &$startTimes,
            &$urlCount,
            &$currentCategory,
            &$lastCallbackTime,
        ): bool {
            $startTimes[$category] = microtime(true);
            $currentCategory = $category;
            $urlCount = 0; // カテゴリごとにリセット
            $lastCallbackTime = null; // カテゴリごとにリセット

            $fileTime = $positionStore->getFileDateTime($category)->format('Y-m-d H:i:s');
            $now = OpenChatServicesUtility::getModifiedCronTime('now')->format('Y-m-d H:i:s');
            $isDownloadedCategory = $fileTime === $now;

            if ($isDownloadedCategory) {
                CronUtility::addVerboseCronLog(
                    $this->getCategoryLabel($category, $positionStore) . "は最新のためスキップ（取得日時: " . substr($fileTime, 0, -3) . "）"
                );
            } else {
                CronUtility::addVerboseCronLog($this->getCategoryLabel($category, $positionStore) . "を取得中");
            }

            return $isDownloadedCategory;
        };

        $callbackByCategoryAfter = function (string $category) use ($positionStore, &$startTimes): void {
            $label = $this->getCategoryLabel($category, $positionStore) . $positionStore->getCacheCount() . "件";
            $elapsed = isset($startTimes[$category]) ? "（" . formatElapsedTime($startTimes[$category]) . "）" : '';
            CronUtility::addVerboseCronLog("{$label}取得完了{$elapsed}");

            $positionStore->clearAllCacheDataAndSaveCurrentCategoryApiDataCache($category);
        };

        return $downloader->fetchOpenChatApiRankingAll($callback, $callbackByCategoryBefore, $callbackByCategoryAfter);
    }

    /** @throws ApplicationException */
    private function checkKillFlag()
    {
        $this->syncOpenChatStateRepository->getBool(SyncOpenChatStateType::openChatApiDbMergerKillFlag)
            && throw new ApplicationException('OpenChatApiDbMerger: 強制終了しました');
    }

    static function setKillFlagTrue()
    {
        /** @var SyncOpenChatStateRepositoryInterface $syncOpenChatStateRepository */
        $syncOpenChatStateRepository = app(SyncOpenChatStateRepositoryInterface::class);
        $syncOpenChatStateRepository->setTrue(SyncOpenChatStateType::openChatApiDbMergerKillFlag);
    }

    static function setKillFlagFalse()
    {
        /** @var SyncOpenChatStateRepositoryInterface $syncOpenChatStateRepository */
        $syncOpenChatStateRepository = app(SyncOpenChatStateRepositoryInterface::class);
        $syncOpenChatStateRepository->setFalse(SyncOpenChatStateType::openChatApiDbMergerKillFlag);
    }
}

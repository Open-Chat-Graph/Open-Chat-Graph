<?php

declare(strict_types=1);

namespace App\Services\OpenChat;

use App\Config\AppConfig;
use App\Services\OpenChat\Crawler\OpenChatApiRankingDownloader;
use App\Services\OpenChat\Crawler\OpenChatApiRankingDownloaderProcess;
use App\Services\OpenChat\Crawler\OpenChatApiRisingDownloaderProcess;
use App\Models\Repositories\Log\LogRepositoryInterface;
use App\Services\RankingPosition\Store\RankingPositionStore;
use App\Services\RankingPosition\Store\RisingPositionStore;
use App\Services\RankingPosition\Store\AbstractRankingPositionStore;
use App\Services\OpenChat\Updater\Process\OpenChatApiDbMergerProcess;
use App\Services\OpenChat\Dto\OpenChatApiDtoFactory;
use App\Services\OpenChat\Dto\OpenChatDto;
use App\Exceptions\ApplicationException;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use Shadow\DB;

class OpenChatApiDbMerger
{
    private OpenChatApiRankingDownloader $rankingDownloader;
    private OpenChatApiRankingDownloader $risingDownloader;

    function __construct(
        private OpenChatApiDtoFactory $openChatApiDtoFactory,
        private OpenChatApiDbMergerProcess $openChatApiDbMergerProcess,
        private LogRepositoryInterface $logRepository,
        private RankingPositionStore $rankingStore,
        private RisingPositionStore $risingStore,
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
     * @return array{ count: int, category: string, dateTime: \DateTime }[] 取得済件数とカテゴリ
     * @throws \RuntimeException
     */
    function fetchOpenChatApiRankingAll(): array
    {
        try {
            $result1 = $this->fetchOpenChatApiRankingAllProcess($this->risingStore, $this->risingDownloader);
            $result2 = $this->fetchOpenChatApiRankingAllProcess($this->rankingStore, $this->rankingDownloader);
            return [...$result1, ...$result2];
        } catch (\RuntimeException $e) {
            // 再接続
            DB::$pdo = null;
            $this->logRepository->logUpdateOpenChatError(0, $e->__toString());
            throw $e;
        }
    }

    private function fetchOpenChatApiRankingAllProcess(
        AbstractRankingPositionStore $positionStore,
        OpenChatApiRankingDownloader $downloader
    ): array {
        // API OC一件ずつの処理
        $processCallback = function (OpenChatDto $apiDto) use ($positionStore): ?string {
            $positionStore->addApiDto($apiDto);
            return $this->openChatApiDbMergerProcess->validateAndMapToOpenChatDtoCallback($apiDto);
        };

        // API URL一件ずつの処理 
        $callback = function (array $apiData) use ($processCallback): void {
            $this->checkKillFlag();

            $errors = $this->openChatApiDtoFactory->validateAndMapToOpenChatDto($apiData, $processCallback);

            foreach ($errors as $error) {
                // 再接続
                DB::$pdo = null;
                $this->logRepository->logUpdateOpenChatError(0, $error);
            }
        };

        // API カテゴリごとの処理
        $callbackByCategoryBefore = function (string $category) use ($positionStore): bool {
            $fileTime = $positionStore->getFileDateTime($category)->format('Y-m-d H:i:s');
            $now = OpenChatServicesUtility::getModifiedCronTime('now')->format('Y-m-d H:i:s');
            return $fileTime === $now;
        };

        $callbackByCategoryAfter = function (string $category) use ($positionStore): void {
            $positionStore->clearAllCacheDataAndSaveCurrentCategoryApiDataCache($category);
        };

        return $downloader->fetchOpenChatApiRankingAll($callback, $callbackByCategoryBefore, $callbackByCategoryAfter);
    }

    static function checkKillFlag()
    {
        clearstatcache(true, AppConfig::OPEN_CHAT_API_DB_MERGER_KILL_FLAG_PATH);
        if (file_get_contents(AppConfig::OPEN_CHAT_API_DB_MERGER_KILL_FLAG_PATH) === '1') {
            throw new ApplicationException('OpenChatApiDbMerger: 強制終了しました');
        }
    }

    static function disableKillFlag()
    {
        file_put_contents(AppConfig::OPEN_CHAT_API_DB_MERGER_KILL_FLAG_PATH, '0');
    }

    static function enableKillFlag()
    {
        file_put_contents(AppConfig::OPEN_CHAT_API_DB_MERGER_KILL_FLAG_PATH, '1');
    }
}

<?php

declare(strict_types=1);

namespace App\Services\OpenChat;

use App\Services\OpenChat\Crawler\OpenChatApiRankingDownloader;
use App\Services\OpenChat\Crawler\OpenChatApiRankingDownloaderProcess;
use App\Services\OpenChat\Crawler\OpenChatApiRisingDownloaderProcess;
use App\Models\Repositories\Log\LogRepositoryInterface;
use App\Services\RankingPosition\Store\RankingPositionStore;
use App\Services\RankingPosition\Store\RisingPositionStore;
use App\Services\RankingPosition\Store\AbstractRankingPositionStore;
use App\Services\OpenChat\Dto\OpenChatApiDtoFactory;
use App\Services\OpenChat\Dto\OpenChatDto;
use App\Exceptions\ApplicationException;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Cron\Enum\SyncOpenChatStateType;
use App\Services\OpenChat\Enum\RankingType;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;

class OpenChatApiDataParallelDownloader
{
    private OpenChatApiRankingDownloader $rankingDownloader;
    private OpenChatApiRankingDownloader $risingDownloader;

    function __construct(
        private OpenChatApiDtoFactory $openChatApiDtoFactory,
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
     * @return int 取得済件数
     * @throws \RuntimeException
     */
    function fetchOpenChatApi(RankingType $type, int $category): int
    {
        $args = match ($type) {
            RankingType::Rising => [$this->risingStore, $this->risingDownloader],
            RankingType::Ranking => [$this->rankingStore, $this->rankingDownloader],
        };

        try {
            $result = $this->process((string)$category, ...$args);
        } catch (\RuntimeException $e) {
            $this->logRepository->logUpdateOpenChatError(0, $e->__toString());
            throw $e;
        }

        return $result;
    }

    private function process(
        string $category,
        AbstractRankingPositionStore $positionStore,
        OpenChatApiRankingDownloader $downloader
    ): int {
        // API カテゴリごとの前処理
        $fileTime = $positionStore->getFileDateTime($category)->format('Y-m-d H:i:s');
        $now = OpenChatServicesUtility::getModifiedCronTime('now')->format('Y-m-d H:i:s');
        // 取得済の場合
        if ($fileTime === $now) {
            return 0;
        }

        // API OC一件ずつの処理
        $processCallback = function (OpenChatDto $apiDto) use ($positionStore) {
            $positionStore->addApiDto($apiDto);
            return null;
        };

        // API URL一件ずつの処理 
        $callback = function (array $apiData) use ($processCallback): void {
            $this->checkKillFlag();

            $errors = $this->openChatApiDtoFactory->validateAndMapToOpenChatDto($apiData, $processCallback);

            foreach ($errors as $error) {
                $this->logRepository->logUpdateOpenChatError(0, $error);
            }

            if ($errors) {
                throw new \RuntimeException('validateAndMapToOpenChatDto: ' . implode(',', $errors));
            }
        };

        $resultCount = $downloader->fetchOpenChatApiRanking($category, $callback);

        // API カテゴリごとの後処理
        $positionStore->clearAllCacheDataAndSaveCurrentCategoryApiDataCache($category);

        return $resultCount;
    }

    /** @throws ApplicationException */
    private function checkKillFlag()
    {
        $this->syncOpenChatStateRepository->getBool(SyncOpenChatStateType::openChatApiDbMergerKillFlag)
            && throw new ApplicationException('OpenChatApiDataParallelDownloader: 強制終了しました');
    }
}

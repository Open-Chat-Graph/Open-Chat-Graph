<?php

use PHPUnit\Framework\TestCase;
use App\Services\OpenChat\Crawler\OpenChatApiRankingDownloader;
use App\Services\RankingPosition\Store\RankingPositionStore;
use App\Services\OpenChat\Crawler\OpenChatApiRankingDownloaderProcess;
use App\Services\OpenChat\Dto\OpenChatApiDtoFactory;
use App\Services\OpenChat\Dto\OpenChatDto;
use App\Services\RankingPosition\RankingPositionHourUpdater;

class RankingPositionHourUpdaterTest extends TestCase
{
    private RankingPositionStore $rankingPositionStore;
    private OpenChatApiRankingDownloader $openChatApiRankingDataDownloader;
    private OpenChatApiDtoFactory $openChatApiDtoFactory;
    private RankingPositionHourUpdater $rankingPositionHourUpdater;

    public function testcrawlRisingAndUpdateRankingPositionHourDb()
    {
        $this->rankingPositionStore = app(RankingPositionStore::class);
        $this->openChatApiRankingDataDownloader = app(
            OpenChatApiRankingDownloader::class,
            ['openChatApiRankingDownloaderProcess' => app(OpenChatApiRankingDownloaderProcess::class)]
        );

        $this->openChatApiDtoFactory = app(OpenChatApiDtoFactory::class);

        // API OC一件ずつの処理
        $processCallback = function (OpenChatDto $apiDto): ?string {
            $this->rankingPositionStore->addApiDto($apiDto);
            return null;
        };

        // API URL一件ずつの処理
        $callback = function (array $apiData) use ($processCallback): void {
            $this->openChatApiDtoFactory->validateAndMapToOpenChatDto($apiData, $processCallback);
        };

        // API カテゴリごとの処理
        $callbackByCategory = function (string $category): void {
            $this->rankingPositionStore->saveClearCurrentCategoryApiDataCache($category);
        };

        $this->openChatApiRankingDataDownloader->fetchOpenChatApiRankingAll(100, 1, $callback, $callbackByCategory);

        $this->rankingPositionHourUpdater = app(RankingPositionHourUpdater::class);
        $this->rankingPositionHourUpdater->crawlRisingAndUpdateRankingPositionHourDb();

        $this->assertTrue(true);
    }
}

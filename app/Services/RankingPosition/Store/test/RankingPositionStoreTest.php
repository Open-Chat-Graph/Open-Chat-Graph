<?php

use PHPUnit\Framework\TestCase;
use App\Services\OpenChat\Crawler\OpenChatApiRankingDownloader;
use App\Services\RankingPosition\Store\RankingPositionStore;
use App\Services\OpenChat\Crawler\OpenChatApiRankingDownloaderProcess;
use App\Services\OpenChat\Dto\OpenChatApiDtoFactory;
use App\Services\OpenChat\Dto\OpenChatDto;
class RankingPositionStoreTest extends TestCase
{
    private RankingPositionStore $rankingPositionStore;
    private OpenChatApiRankingDownloader $openChatApiRankingDataDownloader;
    private OpenChatApiDtoFactory $openChatApiDtoFactory;
    public function testfetchSaveOpenChatRankingApiData()
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
            $errors = $this->openChatApiDtoFactory->validateAndMapToOpenChatDto($apiData, $processCallback);
            $this->assertEmpty($errors);
            debug($errors);
        };

        // API カテゴリごとの処理
        $callbackByCategory = function (string $category): void {
            $this->rankingPositionStore->saveClearCurrentCategoryApiDataCache($category);
        };

        $result = $this->openChatApiRankingDataDownloader->fetchOpenChatApiRankingAll(100, 1, $callback, $callbackByCategory);

        debug($result);
        $this->assertTrue(true);
    }

    private function testShowPositionData(string $fileName)
    {
        $data = getUnserializedFile($fileName, true);
        var_dump($data);
        $this->assertIsArray($data);
    }
}

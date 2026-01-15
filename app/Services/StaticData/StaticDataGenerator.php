<?php

declare(strict_types=1);

namespace App\Services\StaticData;

use App\Config\AppConfig;
use App\Models\RecommendRepositories\RecommendRankingRepository;
use App\Models\Repositories\OpenChatListRepositoryInterface;
use App\Services\Recommend\TopPageRecommendList;
use App\Services\StaticData\Dto\StaticRecommendPageDto;
use App\Services\StaticData\Dto\StaticTopPageDto;
use App\Services\Storage\FileStorageInterface;
use App\Views\Dto\RankingArgDto;
use Shared\MimimalCmsConfig;

class StaticDataGenerator
{
    function __construct(
        private OpenChatListRepositoryInterface $openChatListRepository,
        private TopPageRecommendList $topPageRecommendList,
        private RecommendRankingRepository $recommendPageRepository,
        private FileStorageInterface $fileStorage,
    ) {}

    function getTopPageDataFromDB(): StaticTopPageDto
    {
        // トップページのキャッシュファイルを生成する
        $dto = new StaticTopPageDto;
        $dto->hourlyList = $this->openChatListRepository->findMemberStatsHourlyRanking(0, AppConfig::$listLimitTopRanking);
        $dto->dailyList = $this->openChatListRepository->findMemberStatsDailyRanking(0, AppConfig::$listLimitTopRanking);
        $dto->weeklyList = $this->openChatListRepository->findMemberStatsPastWeekRanking(0, AppConfig::$listLimitTopRanking);
        $dto->popularList = $this->openChatListRepository->findMemberCountRanking(AppConfig::$listLimitTopRanking, []);
        $dto->recentCommentList = [];
        $dto->recommendList = $this->topPageRecommendList->getList(30);

        $dto->hourlyUpdatedAt = new \DateTime($this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
        $dto->dailyUpdatedAt = new \DateTime($this->fileStorage->getContents('@dailyCronUpdatedAtDate'));
        $dto->rankingUpdatedAt = new \DateTime($this->fileStorage->getContents('@hourlyRealUpdatedAtDatetime'));

        $tagList = $this->fileStorage->getSerializedFile('@tagList');
        if (!$tagList)
            $tagList = $this->getTagList();

        $dto->tagCount = array_sum(array_map(fn($el) => count($el), $tagList));

        return $dto;
    }

    function getRankingArgDto(): RankingArgDto
    {
        $_argDto = new RankingArgDto;
        $_argDto->urlRoot = MimimalCmsConfig::$urlRoot;
        $_argDto->baseUrl = url();
        $_argDto->rankingUpdatedAt = convertDatetime($this->fileStorage->getContents('@hourlyRealUpdatedAtDatetime'), true);
        $_argDto->hourlyUpdatedAt = $this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime');
        $_argDto->modifiedUpdatedAtDate = $this->fileStorage->getContents('@dailyCronUpdatedAtDate');

        $_argDto->openChatCategory = [];
        foreach (AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] as $name => $number) {
            if ($number === 0)
                $_argDto->openChatCategory[] = [$name, $number];
        }
        foreach (AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] as $name => $number) {
            if ($number !== 0)
                $_argDto->openChatCategory[] = [$name, $number];
        }

        $path = \App\Services\Storage\FileStorageService::getStorageFilePath('openChatSubCategories');
        $subCategories = json_decode(
            file_exists($path)
                ? $this->fileStorage->getContents('@openChatSubCategories')
                : '{}',
            true
        );
        $_argDto->subCategories = $this->replaceSubcategoryName($subCategories);

        return $_argDto;
    }

    private function replaceSubcategoryName(array $subCategories): array
    {
        switch (MimimalCmsConfig::$urlRoot) {
            case '':
                if (isset($subCategories[6])) {
                    $key = array_search('オプチャ宣伝', $subCategories[6]);
                    if ($key !== false) {
                        $subCategories[6][$key] = 'オプチャ 宣伝';
                    }

                    $key = array_search('悩み相談', $subCategories[6]);
                    if ($key !== false) {
                        $subCategories[6][$key] = '悩み 相談';
                    }
                }
                break;
            case '/tw':
                break;
            case '/th':
                break;
        }

        return $subCategories;
    }

    function getRecommendPageDto(): StaticRecommendPageDto
    {
        $tagList = $this->fileStorage->getSerializedFile('@tagList');
        if (!$tagList)
            $tagList = $this->getTagList();

        $dto = new StaticRecommendPageDto;
        $dto->hourlyUpdatedAt = $this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime');
        $dto->tagCount = array_sum(array_map(fn($el) => count($el), $tagList));

        $dto->tagRecordCounts = [];
        array_map(
            fn($row) => $dto->tagRecordCounts[$row['tag']] = $row['record_count'],
            $this->recommendPageRepository->getRecommendTagRecordCountAllRoom()
        );

        return $dto;
    }

    function getTagList(): array
    {
        return $this->recommendPageRepository->getRecommendTagAndCategoryAll();
    }

    function updateStaticData()
    {
        $this->fileStorage->safeFileRewrite('@hourlyRealUpdatedAtDatetime', (new \DateTime)->format('Y-m-d H:i:s'));
        $this->fileStorage->saveSerializedFile('@tagList', $this->getTagList());
        $this->fileStorage->saveSerializedFile('@topPageRankingData', $this->getTopPageDataFromDB());
        $this->fileStorage->saveSerializedFile('@rankingArgDto', $this->getRankingArgDto());
        $this->fileStorage->saveSerializedFile('@recommendPageDto', $this->getRecommendPageDto());
    }
}

<?php

declare(strict_types=1);

namespace App\Services\StaticData;

use App\Config\AppConfig;
use App\Models\RecommendRepositories\RecommendRankingRepository;
use App\Models\Repositories\OpenChatListRepositoryInterface;
use App\Services\Recommend\TopPageRecommendList;
use App\Services\StaticData\Dto\StaticRecommendPageDto;
use App\Services\StaticData\Dto\StaticTopPageDto;
use App\Views\Dto\RankingArgDto;

class StaticDataGenerator
{
    function __construct(
        private OpenChatListRepositoryInterface $openChatListRepository,
        private TopPageRecommendList $topPageRecommendList,
        private RecommendRankingRepository $recommendPageRepository,
    ) {}

    function getTopPageDataFromDB(): StaticTopPageDto
    {
        // トップページのキャッシュファイルを生成する
        $dto = new StaticTopPageDto;
        $dto->hourlyList = $this->openChatListRepository->findMemberStatsHourlyRanking(0, AppConfig::TOP_RANKING_LIST_LIMIT);
        $dto->dailyList = $this->openChatListRepository->findMemberStatsDailyRanking(0, AppConfig::TOP_RANKING_LIST_LIMIT);
        $dto->weeklyList = $this->openChatListRepository->findMemberStatsPastWeekRanking(0, AppConfig::TOP_RANKING_LIST_LIMIT);
        $dto->popularList = $this->openChatListRepository->findMemberCountRanking(AppConfig::TOP_RANKING_LIST_LIMIT, []);
        $dto->recentCommentList = [];
        $dto->recommendList = $this->topPageRecommendList->getList(30);

        $dto->hourlyUpdatedAt = new \DateTime(file_get_contents(getStorageFilePath(AppConfig::STORAGE_FILES['hourlyCronUpdatedAtDatetime'])));
        $dto->dailyUpdatedAt = new \DateTime(file_get_contents(getStorageFilePath(AppConfig::STORAGE_FILES['dailyCronUpdatedAtDate'])));
        $dto->rankingUpdatedAt = new \DateTime(file_get_contents(getStorageFilePath(AppConfig::STORAGE_FILES['hourlyRealUpdatedAtDatetime'])));

        $tagList = getUnserializedFile(getStorageFilePath(AppConfig::STORAGE_FILES['tagList']));
        if (!$tagList)
            $tagList = $this->getTagList();

        $dto->tagCount = array_sum(array_map(fn($el) => count($el), $tagList));

        return $dto;
    }

    function getRankingArgDto(): RankingArgDto
    {
        $_argDto = new RankingArgDto;
        $_argDto->rankingUpdatedAt = convertDatetime(file_get_contents(getStorageFilePath(AppConfig::STORAGE_FILES['hourlyRealUpdatedAtDatetime'])), true);
        $_argDto->hourlyUpdatedAt = file_get_contents(getStorageFilePath(AppConfig::STORAGE_FILES['hourlyCronUpdatedAtDatetime']));
        $_argDto->modifiedUpdatedAtDate = file_get_contents(getStorageFilePath(AppConfig::STORAGE_FILES['dailyCronUpdatedAtDate']));;
        $_argDto->subCategories = json_decode(file_get_contents(getStorageFilePath(AppConfig::STORAGE_FILES['openChatSubCategories'])), true);

        if (isset($_argDto->subCategories[6])) {
            $key = array_search('オプチャ宣伝', $_argDto->subCategories[6]);
            if ($key !== false) {
                $_argDto->subCategories[6][$key] = 'オプチャ 宣伝';
            }

            $key = array_search('悩み相談', $_argDto->subCategories[6]);
            if ($key !== false) {
                $_argDto->subCategories[6][$key] = '悩み 相談';
            }

            $_argDto->subCategories[6] = array_values($_argDto->subCategories[6]);
        }

        return $_argDto;
    }

    function getRecommendPageDto(): StaticRecommendPageDto
    {
        $tagList = getUnserializedFile(getStorageFilePath(AppConfig::STORAGE_FILES['tagList']));
        if (!$tagList)
            $tagList = $this->getTagList();

        $dto = new StaticRecommendPageDto;
        $dto->hourlyUpdatedAt = file_get_contents(getStorageFilePath(AppConfig::STORAGE_FILES['hourlyCronUpdatedAtDatetime']));
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
        safeFileRewrite(getStorageFilePath(AppConfig::STORAGE_FILES['hourlyRealUpdatedAtDatetime']), (new \DateTime)->format('Y-m-d H:i:s'));
        saveSerializedFile(getStorageFilePath(AppConfig::STORAGE_FILES['tagList']), $this->getTagList());
        saveSerializedFile(getStorageFilePath(AppConfig::STORAGE_FILES['topPageRankingData']), $this->getTopPageDataFromDB());
        saveSerializedFile(getStorageFilePath(AppConfig::STORAGE_FILES['rankingArgDto']), $this->getRankingArgDto());
        saveSerializedFile(getStorageFilePath(AppConfig::STORAGE_FILES['recommendPageDto']), $this->getRecommendPageDto());
    }
}

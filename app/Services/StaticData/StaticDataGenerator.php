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
use Shared\MimimalCmsConfig;

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
        $dto->hourlyList = $this->openChatListRepository->findMemberStatsHourlyRanking(0, AppConfig::$listLimitTopRanking);
        $dto->dailyList = $this->openChatListRepository->findMemberStatsDailyRanking(0, AppConfig::$listLimitTopRanking);
        $dto->weeklyList = $this->openChatListRepository->findMemberStatsPastWeekRanking(0, AppConfig::$listLimitTopRanking);
        $dto->popularList = $this->openChatListRepository->findMemberCountRanking(AppConfig::$listLimitTopRanking, []);
        $dto->recentCommentList = [];
        $dto->recommendList = $this->topPageRecommendList->getList(30);

        $dto->hourlyUpdatedAt = new \DateTime(file_get_contents(AppConfig::getStorageFilePath('hourlyCronUpdatedAtDatetime')));
        $dto->dailyUpdatedAt = new \DateTime(file_get_contents(AppConfig::getStorageFilePath('dailyCronUpdatedAtDate')));
        $dto->rankingUpdatedAt = new \DateTime(file_get_contents(AppConfig::getStorageFilePath('hourlyRealUpdatedAtDatetime')));

        $tagList = getUnserializedFile(AppConfig::getStorageFilePath('tagList'));
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
        $_argDto->rankingUpdatedAt = convertDatetime(file_get_contents(AppConfig::getStorageFilePath('hourlyRealUpdatedAtDatetime')), true);
        $_argDto->hourlyUpdatedAt = file_get_contents(AppConfig::getStorageFilePath('hourlyCronUpdatedAtDatetime'));
        $_argDto->modifiedUpdatedAtDate = file_get_contents(AppConfig::getStorageFilePath('dailyCronUpdatedAtDate'));

        $_argDto->openChatCategory = [];
        foreach (AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] as $name => $number) {
            if ($number === 0)
                $_argDto->openChatCategory[] = [$name, $number];
        }
        foreach (AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] as $name => $number) {
            if ($number !== 0)
                $_argDto->openChatCategory[] = [$name, $number];
        }

        $subCategories = json_decode(
            file_exists(AppConfig::getStorageFilePath('openChatSubCategories'))
                ? file_get_contents(AppConfig::getStorageFilePath('openChatSubCategories'))
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
        $tagList = getUnserializedFile(AppConfig::getStorageFilePath('tagList'));
        if (!$tagList)
            $tagList = $this->getTagList();

        $dto = new StaticRecommendPageDto;
        $dto->hourlyUpdatedAt = file_get_contents(AppConfig::getStorageFilePath('hourlyCronUpdatedAtDatetime'));
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
        safeFileRewrite(AppConfig::getStorageFilePath('hourlyRealUpdatedAtDatetime'), (new \DateTime)->format('Y-m-d H:i:s'));
        saveSerializedFile(AppConfig::getStorageFilePath('tagList'), $this->getTagList());
        saveSerializedFile(AppConfig::getStorageFilePath('topPageRankingData'), $this->getTopPageDataFromDB());
        saveSerializedFile(AppConfig::getStorageFilePath('rankingArgDto'), $this->getRankingArgDto());
        saveSerializedFile(AppConfig::getStorageFilePath('recommendPageDto'), $this->getRecommendPageDto());
    }
}

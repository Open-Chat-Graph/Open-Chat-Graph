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
    ) {
    }

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

        $dto->hourlyUpdatedAt = new \DateTime(file_get_contents(AppConfig::$HOURLY_CRON_UPDATED_AT_DATETIME));
        $dto->dailyUpdatedAt = new \DateTime(file_get_contents(AppConfig::$DAILY_CRON_UPDATED_AT_DATE));
        $dto->rankingUpdatedAt = new \DateTime(file_get_contents(AppConfig::$HOURLY_REAL_UPDATED_AT_DATETIME));

        $tagList = getUnserializedFile('static_data_top/tag_list.dat');
        if (!$tagList)
            $tagList = $this->getTagList();

        $dto->tagCount = array_sum(array_map(fn ($el) => count($el), $tagList));

        return $dto;
    }

    function getRankingArgDto(): RankingArgDto
    {
        $_argDto = new RankingArgDto;
        $_argDto->rankingUpdatedAt = convertDatetime(file_get_contents(AppConfig::$HOURLY_REAL_UPDATED_AT_DATETIME), true);
        $_argDto->hourlyUpdatedAt = file_get_contents(AppConfig::$HOURLY_CRON_UPDATED_AT_DATETIME);
        $_argDto->modifiedUpdatedAtDate = file_get_contents(AppConfig::$DAILY_CRON_UPDATED_AT_DATE);;
        $_argDto->subCategories = json_decode(file_get_contents(AppConfig::$OPEN_CHAT_SUB_CATEGORIES_FILE_PATH), true);

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
        $tagList = getUnserializedFile('static_data_top/tag_list.dat');
        if (!$tagList)
            $tagList = $this->getTagList();

        $dto = new StaticRecommendPageDto;
        $dto->hourlyUpdatedAt = file_get_contents(AppConfig::$HOURLY_CRON_UPDATED_AT_DATETIME);
        $dto->tagCount = array_sum(array_map(fn ($el) => count($el), $tagList));

        $dto->tagRecordCounts = [];
        array_map(
            fn ($row) => $dto->tagRecordCounts[$row['tag']] = $row['record_count'],
            $this->recommendPageRepository->getRecommendTagRecordCountAllRoom()
        );

        return $dto;
    }

    function getTagList(): array
    {
        return $this->recommendPageRepository->getRecommendTagAndCategoryAll();
    }
// static_data_topのキャッシュファイルの多言語対応
    function updateStaticData()
    {
        safeFileRewrite(AppConfig::$HOURLY_REAL_UPDATED_AT_DATETIME, (new \DateTime)->format('Y-m-d H:i:s'));
        saveSerializedFile('static_data_top/tag_list.dat', $this->getTagList());
        saveSerializedFile('static_data_top/ranking_list.dat', $this->getTopPageDataFromDB());
        saveSerializedFile('static_data_top/ranking_arg_dto.dat', $this->getRankingArgDto());
        saveSerializedFile('static_data_top/recommend_page_dto.dat', $this->getRecommendPageDto());
    }
}

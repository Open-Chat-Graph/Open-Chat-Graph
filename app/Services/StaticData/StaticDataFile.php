<?php

declare(strict_types=1);

namespace App\Services\StaticData;

use App\Config\AppConfig;
use App\Services\StaticData\Dto\StaticRecommendPageDto;
use App\Services\StaticData\Dto\StaticTopPageDto;
use App\Views\Dto\RankingArgDto;

class StaticDataFile
{
    private function checkUpdatedAt(string $hourlyUpdatedAt)
    {
        if (!$hourlyUpdatedAt === getHouryUpdateTime())
            noStore();
    }

    function getTopPageData(): StaticTopPageDto
    {
        $data = getUnserializedFile(AppConfig::getStorageFilePath('topPageRankingData'));

        /** @var StaticTopPageDto $data */
        if (!$data) {
            /** @var StaticDataGenerator $staticDataGenerator */
            $staticDataGenerator = app(StaticDataGenerator::class);
            return $staticDataGenerator->getTopPageDataFromDB();
        }

        $this->checkUpdatedAt($data->hourlyUpdatedAt->format('Y-m-d H:i:s'));
        return $data;
    }

    function getRankingArgDto(): RankingArgDto
    {
        /** @var RankingArgDto $data */
        $data = getUnserializedFile(AppConfig::getStorageFilePath('rankingArgDto'));
        //$data = null;
        if (!$data) {
            /** @var StaticDataGenerator $staticDataGenerator */
            $staticDataGenerator = app(StaticDataGenerator::class);
            $data = $staticDataGenerator->getRankingArgDto();
        }

        $this->checkUpdatedAt($data->hourlyUpdatedAt);
        return $data;
    }

    function getRecommendPageDto(): StaticRecommendPageDto
    {
        /** @var StaticRecommendPageDto $data */
        $data = getUnserializedFile(AppConfig::getStorageFilePath('recommendPageDto'));
        //$data = null;
        if (!$data) {
            /** @var StaticDataGenerator $staticDataGenerator */
            $staticDataGenerator = app(StaticDataGenerator::class);
            $data = $staticDataGenerator->getRecommendPageDto();
        }

        $this->checkUpdatedAt($data->hourlyUpdatedAt);
        return $data;
    }

    /** @return array<int, array<array{tag:string, record_count:int}>> */
    function getTagList(): array
    {
        /** @var array $data */
        $data = getUnserializedFile(AppConfig::getStorageFilePath('tagList'));
        if (!$data) {
            /** @var StaticDataGenerator $staticDataGenerator */
            $staticDataGenerator = app(StaticDataGenerator::class);
            $data = $staticDataGenerator->getTagList();
        }

        $time = getStorageFileTime(AppConfig::getStorageFilePath('tagList'));
        if (!$time || new \DateTime('@' . $time) < new \DateTime(getHouryUpdateTime()))
            noStore();

        return $data;
    }
}

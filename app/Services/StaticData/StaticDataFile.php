<?php

declare(strict_types=1);

namespace App\Services\StaticData;

use App\Config\AppConfig;
use App\Services\StaticData\Dto\StaticRecommendPageDto;
use App\Services\StaticData\Dto\StaticTopPageDto;
use App\Services\Storage\FileStorageInterface;
use App\Views\Dto\RankingArgDto;

class StaticDataFile
{
    public function __construct(
        private FileStorageInterface $fileStorage
    ) {}
    private function checkUpdatedAt(string $hourlyUpdatedAt)
    {
        if (!$hourlyUpdatedAt === $this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime'))
            noStore();
    }

    function getTopPageData(): StaticTopPageDto
    {
        $data = $this->fileStorage->getSerializedFile('@topPageRankingData');

        /** @var StaticTopPageDto $data */
        if (!$data || AppConfig::$disableStaticDataFile) {
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
        $data = $this->fileStorage->getSerializedFile('@rankingArgDto');
        //$data = null;
        if (!$data || AppConfig::$disableStaticDataFile) {
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
        $data = $this->fileStorage->getSerializedFile('@recommendPageDto');
        //$data = null;
        if (!$data || AppConfig::$disableStaticDataFile) {
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
        $data = $this->fileStorage->getSerializedFile('@tagList');
        if (!$data || AppConfig::$disableStaticDataFile) {
            /** @var StaticDataGenerator $staticDataGenerator */
            $staticDataGenerator = app(StaticDataGenerator::class);
            $data = $staticDataGenerator->getTagList();
        }

        $time = getStorageFileTime(\App\Services\Storage\FileStorageService::getStorageFilePath('tagList'));
        if (!$time || new \DateTime('@' . $time) < new \DateTime($this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime')))
            noStore();

        return $data;
    }
}

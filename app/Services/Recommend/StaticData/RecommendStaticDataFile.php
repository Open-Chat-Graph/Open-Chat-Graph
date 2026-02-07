<?php

declare(strict_types=1);

namespace App\Services\Recommend\StaticData;

use App\Config\AppConfig;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Storage\FileStorageInterface;

class RecommendStaticDataFile
{
    public function __construct(
        private FileStorageInterface $fileStorage
    ) {}

    private function checkUpdatedAt(RecommendListDto $data)
    {
        if (
            !$data->getCount()
            || !$data->hourlyUpdatedAt === $this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime')
        )
            noStore();
    }

    function getCategoryRanking(int $category): RecommendListDto
    {
        $data = $this->fileStorage->getSerializedFile(
            $this->fileStorage->getStorageFilePath('categoryStaticDataDir') . "/{$category}.dat"
        );

        if (!$data || AppConfig::$disableStaticDataFile) {
            /** @var RecommendStaticDataGenerator $staticDataGenerator */
            $staticDataGenerator = app(RecommendStaticDataGenerator::class);
            return $staticDataGenerator->getCategoryRanking($category);
        }

        $this->checkUpdatedAt($data);
        return $data;
    }

    function getRecomendRanking(string $tag): RecommendListDto
    {
        $fileName = hash('crc32', $tag);
        $data = $this->fileStorage->getSerializedFile(
            $this->fileStorage->getStorageFilePath('recommendStaticDataDir') . "/{$fileName}.dat"
        );

        if (!$data || AppConfig::$disableStaticDataFile) {
            /** @var RecommendStaticDataGenerator $staticDataGenerator */
            $staticDataGenerator = app(RecommendStaticDataGenerator::class);
            return $staticDataGenerator->getRecomendRanking($tag);
        }

        $this->checkUpdatedAt($data);
        return $data;
    }

    function getOfficialRanking(int $emblem): RecommendListDto
    {
        $data = $this->fileStorage->getSerializedFile(
            $this->fileStorage->getStorageFilePath('officialStaticDataDir') . "/{$emblem}.dat"
        );

        if (!$data || AppConfig::$disableStaticDataFile) {
            /** @var RecommendStaticDataGenerator $staticDataGenerator */
            $staticDataGenerator = app(RecommendStaticDataGenerator::class);
            return $staticDataGenerator->getOfficialRanking($emblem);
        }

        $this->checkUpdatedAt($data);
        return $data;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Recommend\StaticData;

use App\Config\AppConfig;
use App\Services\Recommend\Dto\RecommendListDto;

class RecommendStaticDataFile
{
    private function checkUpdatedAt(RecommendListDto $data)
    {
        if (
            !$data->getCount()
            || !$data->hourlyUpdatedAt === file_get_contents(
                AppConfig::getStorageFilePath('hourlyCronUpdatedAtDatetime')
            )
        )
            noStore();
    }

    function getCategoryRanking(int $category): RecommendListDto
    {
        $data = getUnserializedFile(
            AppConfig::getStorageFilePath('categoryStaticDataDir') . "/{$category}.dat"
        );

        if (!$data) {
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
        $data = getUnserializedFile(
            AppConfig::getStorageFilePath('recommendStaticDataDir') . "/{$fileName}.dat"
        );

        if (!$data) {
            /** @var RecommendStaticDataGenerator $staticDataGenerator */
            $staticDataGenerator = app(RecommendStaticDataGenerator::class);
            return $staticDataGenerator->getRecomendRanking($tag);
        }

        $this->checkUpdatedAt($data);
        return $data;
    }

    function getOfficialRanking(int $emblem): RecommendListDto
    {
        $data = getUnserializedFile(
            AppConfig::getStorageFilePath('officialStaticDataDir') . "/{$emblem}.dat"
        );

        if (!$data) {
            /** @var RecommendStaticDataGenerator $staticDataGenerator */
            $staticDataGenerator = app(RecommendStaticDataGenerator::class);
            return $staticDataGenerator->getOfficialRanking($emblem);
        }

        $this->checkUpdatedAt($data);
        return $data;
    }
}

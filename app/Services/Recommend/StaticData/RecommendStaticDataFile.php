<?php

declare(strict_types=1);

namespace App\Services\Recommend\StaticData;

use App\Config\AppConfig;
use App\Services\Recommend\Dto\RecommendListDto;
//TODO: リコメンドの言語対応
class RecommendStaticDataFile
{
    private function checkUpdatedAt(RecommendListDto $data)
    {
        if (
            !$data->getCount()
            || !$data->hourlyUpdatedAt === file_get_contents(
                getStorageFilePath(AppConfig::STORAGE_FILES['hourlyCronUpdatedAtDatetime'])
            )
        )
            noStore();
    }

    function getCategoryRanking(int $category): RecommendListDto
    {
        $data = getUnserializedFile(
            getStorageFilePath(AppConfig::STORAGE_FILES['categoryStaticDataDir']) . "/{$category}.dat"
        );

        if (!$data || true) { // キャッシュ無効化中
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
            getStorageFilePath(AppConfig::STORAGE_FILES['recommendStaticDataDir']) . "/{$fileName}.dat"
        );

        if (!$data || true) { // キャッシュ無効化中
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
            getStorageFilePath(AppConfig::STORAGE_FILES['officialStaticDataDir']) . "/{$emblem}.dat"
        );

        if (!$data || true) { // キャッシュ無効化中
            /** @var RecommendStaticDataGenerator $staticDataGenerator */
            $staticDataGenerator = app(RecommendStaticDataGenerator::class);
            return $staticDataGenerator->getOfficialRanking($emblem);
        }

        $this->checkUpdatedAt($data);
        return $data;
    }
}

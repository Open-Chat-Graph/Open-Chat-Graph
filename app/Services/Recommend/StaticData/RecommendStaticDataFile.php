<?php

declare(strict_types=1);

namespace App\Services\Recommend\StaticData;

use App\Config\AppConfig;
use App\Services\Recommend\Dto\RecommendListDto;
//TODO: リコメンドの他言語対応
class RecommendStaticDataFile
{
    private function checkUpdatedAt(RecommendListDto|false $data)
    {
        if (!$data || !$data->hourlyUpdatedAt === file_get_contents(AppConfig::$HOURLY_CRON_UPDATED_AT_DATETIME))
            noStore();
    }

    function getCategoryRanking(int $category): RecommendListDto|false
    {
        $data = getUnserializedFile("static_data_recommend/category/{$category}.dat");

        if (!$data || true) { // キャッシュ無効化中
            /** @var RecommendStaticDataGenerator $staticDataGenerator */
            $staticDataGenerator = app(RecommendStaticDataGenerator::class);
            return $staticDataGenerator->getCategoryRanking($category);
        }

        $this->checkUpdatedAt($data);
        return $data;
    }

    function getRecomendRanking(string $tag): RecommendListDto|false
    {
        $fileName = hash('crc32', $tag);
        $data = getUnserializedFile("static_data_recommend/tag/{$fileName}.dat");

        if (!$data || true) { // キャッシュ無効化中
            /** @var RecommendStaticDataGenerator $staticDataGenerator */
            $staticDataGenerator = app(RecommendStaticDataGenerator::class);
            return $staticDataGenerator->getRecomendRanking($tag);
        }


        $this->checkUpdatedAt($data);
        return $data;
    }

    function getOfficialRanking(int $emblem): RecommendListDto|false
    {
        $data = getUnserializedFile("static_data_recommend/official/{$emblem}.dat");

        if (!$data || true) { // キャッシュ無効化中
            /** @var RecommendStaticDataGenerator $staticDataGenerator */
            $staticDataGenerator = app(RecommendStaticDataGenerator::class);
            return $staticDataGenerator->getOfficialRanking($emblem);
        }

        $this->checkUpdatedAt($data);
        return $data;
    }
}

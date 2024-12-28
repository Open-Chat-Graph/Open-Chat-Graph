<?php

declare(strict_types=1);

namespace App\Services\Recommend\StaticData;

use App\Config\AppConfig;
use App\Models\RecommendRepositories\CategoryRankingRepository;
use App\Models\RecommendRepositories\OfficialRoomRankingRepository;
use App\Models\RecommendRepositories\RecommendRankingRepository;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\Enum\RecommendListType;
use App\Services\Recommend\RecommendRankingBuilder;
use App\Services\Recommend\RecommendUpdater;

class RecommendStaticDataGenerator
{
    function __construct(
        private RecommendRankingRepository $recommendRankingRepository,
        private CategoryRankingRepository $categoryRankingRepository,
        private OfficialRoomRankingRepository $officialRoomRankingRepository,
        private RecommendRankingBuilder $recommendRankingBuilder,
        private RecommendUpdater $recommendUpdater,
    ) {
    }

    function getRecomendRanking(string $tag): RecommendListDto|false
    {
        return $this->recommendRankingBuilder->getRanking(
            RecommendListType::Tag,
            $tag,
            $tag,
            $this->recommendRankingRepository
        );
    }

    function getCategoryRanking(int $category): RecommendListDto|false
    {
        return $this->recommendRankingBuilder->getRanking(
            RecommendListType::Category,
            (string)$category,
            getCategoryName($category),
            $this->categoryRankingRepository
        );
    }

    function getOfficialRanking(int $emblem): RecommendListDto|false
    {
        $listName = match ($emblem) {
            1 => 'スペシャルオープンチャット',
            2 => '公式認証オープンチャット',
            default => ''
        };

        return $listName ? $this->recommendRankingBuilder->getRanking(
            RecommendListType::Official,
            (string)$emblem,
            $listName,
            $this->officialRoomRankingRepository
        ) : false;
    }

    /**
     * @return string[]
     */
    function getAllTagNames(): array
    {
        return $this->recommendUpdater->getAllTagNames();
    }
//TODO: リコメンドの多言語対応
    private function updateRecommendStaticData()
    {
        foreach ($this->getAllTagNames() as $tag) {
            $fileName = hash('crc32', $tag);
            saveSerializedFile(
                "static_data_recommend/tag/{$fileName}.dat",
                $this->getRecomendRanking($tag)
            );
        }
    }

    private function updateCategoryStaticData()
    {
        foreach (AppConfig::$OPEN_CHAT_CATEGORY as $category) {
            saveSerializedFile(
                "static_data_recommend/category/{$category}.dat",
                $this->getCategoryRanking($category)
            );
        }
    }

    private function updateOfficialStaticData()
    {
        foreach ([1, 2] as $emblem) {
            saveSerializedFile(
                "static_data_recommend/official/{$emblem}.dat",
                $this->getOfficialRanking($emblem)
            );
        }
    }

    function updateStaticData()
    {
        $this->updateRecommendStaticData();
        $this->updateCategoryStaticData();
        $this->updateOfficialStaticData();
    }
}

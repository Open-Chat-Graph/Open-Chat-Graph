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
use App\Services\Storage\FileStorageInterface;
use App\Services\Storage\FileStorageService;
use Shared\MimimalCmsConfig;

class RecommendStaticDataGenerator
{
    function __construct(
        private RecommendRankingRepository $recommendRankingRepository,
        private CategoryRankingRepository $categoryRankingRepository,
        private OfficialRoomRankingRepository $officialRoomRankingRepository,
        private RecommendRankingBuilder $recommendRankingBuilder,
        private RecommendUpdater $recommendUpdater,
        private FileStorageInterface $fileStorage,
    ) {}

    function getRecomendRanking(string $tag): RecommendListDto
    {
        return $this->recommendRankingBuilder->getRanking(
            RecommendListType::Tag,
            $tag,
            $tag,
            $this->recommendRankingRepository
        );
    }

    function getCategoryRanking(int $category): RecommendListDto
    {
        return $this->recommendRankingBuilder->getRanking(
            RecommendListType::Category,
            (string)$category,
            getCategoryName($category),
            $this->categoryRankingRepository
        );
    }

    function getOfficialRanking(int $emblem): RecommendListDto
    {
        $listName = match ($emblem) {
            1 => AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][1],
            2 => AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][2],
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

    private function updateRecommendStaticData()
    {
        foreach ($this->getAllTagNames() as $tag) {
            $fileName = hash('crc32', $tag);
            $this->fileStorage->saveSerializedFile(
                FileStorageService::getStorageFilePath('recommendStaticDataDir') . "/{$fileName}.dat",
                $this->getRecomendRanking($tag)
            );
        }
    }

    private function updateCategoryStaticData()
    {
        foreach (AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] as $category) {
            $this->fileStorage->saveSerializedFile(
                FileStorageService::getStorageFilePath('categoryStaticDataDir') . "/{$category}.dat",
                $this->getCategoryRanking($category)
            );
        }
    }

    private function updateOfficialStaticData()
    {
        foreach ([1, 2] as $emblem) {
            $this->fileStorage->saveSerializedFile(
                FileStorageService::getStorageFilePath('officialStaticDataDir') . "/{$emblem}.dat",
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

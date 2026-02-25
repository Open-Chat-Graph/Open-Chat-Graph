<?php

declare(strict_types=1);

namespace App\Services\Recommend\StaticData;

use App\Config\AppConfig;
use App\Models\RecommendRepositories\BulkRankingDataRepositoryInterface;
use App\Models\RecommendRepositories\CategoryRankingRepository;
use App\Models\RecommendRepositories\OfficialRoomRankingRepository;
use App\Models\RecommendRepositories\RecommendRankingRepository;
use App\Services\Recommend\BulkRecommendRankingBuilderInterface;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\Enum\RecommendListType;
use App\Services\Recommend\RecommendRankingBuilder;
use App\Services\Recommend\RecommendUpdater;
use App\Services\Storage\FileStorageInterface;
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
        private BulkRankingDataRepositoryInterface $bulkRankingDataRepository,
        private BulkRecommendRankingBuilderInterface $bulkRecommendRankingBuilder,
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

    function updateStaticData()
    {
        $allData = $this->bulkRankingDataRepository->fetchAll();
        $this->bulkRecommendRankingBuilder->init($allData);

        $this->updateRecommendStaticDataBulk();
        $this->updateCategoryStaticDataBulk();
        $this->updateOfficialStaticDataBulk();
    }

    private function updateRecommendStaticDataBulk(): void
    {
        foreach ($this->getAllTagNames() as $tag) {
            $fileName = hash('crc32', $tag);
            $this->fileStorage->saveSerializedFile(
                $this->fileStorage->getStorageFilePath('recommendStaticDataDir') . "/{$fileName}.dat",
                $this->bulkRecommendRankingBuilder->buildTagRanking($tag, $tag)
            );
        }
    }

    private function updateCategoryStaticDataBulk(): void
    {
        foreach (AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] as $category) {
            $this->fileStorage->saveSerializedFile(
                $this->fileStorage->getStorageFilePath('categoryStaticDataDir') . "/{$category}.dat",
                $this->bulkRecommendRankingBuilder->buildCategoryRanking($category, getCategoryName($category))
            );
        }
    }

    private function updateOfficialStaticDataBulk(): void
    {
        foreach ([1, 2] as $emblem) {
            $listName = match ($emblem) {
                1 => AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][1],
                2 => AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][2],
                default => ''
            };

            if ($listName) {
                $this->fileStorage->saveSerializedFile(
                    $this->fileStorage->getStorageFilePath('officialStaticDataDir') . "/{$emblem}.dat",
                    $this->bulkRecommendRankingBuilder->buildOfficialRanking($emblem, $listName)
                );
            }
        }
    }
}

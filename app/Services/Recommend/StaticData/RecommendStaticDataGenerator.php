<?php

declare(strict_types=1);

namespace App\Services\Recommend\StaticData;

use App\Config\AppConfig;
use App\Models\RecommendRepositories\BulkRankingDataRepositoryInterface;
use App\Services\Recommend\BulkRecommendRankingBuilderInterface;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\RecommendUpdater;
use App\Services\Storage\FileStorageInterface;
use Shared\MimimalCmsConfig;

class RecommendStaticDataGenerator
{
    function __construct(
        private RecommendUpdater $recommendUpdater,
        private FileStorageInterface $fileStorage,
        private BulkRankingDataRepositoryInterface $bulkRankingDataRepository,
        private BulkRecommendRankingBuilderInterface $bulkRecommendRankingBuilder,
    ) {}

    private bool $initialized = false;

    /**
     * ビルダーを一度だけ初期化する。
     * 静的データ(.dat)が未生成のタグをページ表示時にライブ生成するフォールバックで、
     * buildTagRanking 等の前提となる全ランキングデータ(init)が必要なため。
     */
    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->bulkRecommendRankingBuilder->init($this->bulkRankingDataRepository->fetchAll());
        $this->initialized = true;
    }

    function getRecomendRanking(string $tag): RecommendListDto
    {
        $this->ensureInitialized();
        return $this->bulkRecommendRankingBuilder->buildTagRanking($tag, $tag);
    }

    function getCategoryRanking(int $category): RecommendListDto
    {
        $this->ensureInitialized();
        return $this->bulkRecommendRankingBuilder->buildCategoryRanking($category, getCategoryName($category));
    }

    function getOfficialRanking(int $emblem): RecommendListDto|false
    {
        $this->ensureInitialized();
        $listName = match ($emblem) {
            1 => AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][1],
            2 => AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][2],
            default => ''
        };

        return $listName ? $this->bulkRecommendRankingBuilder->buildOfficialRanking($emblem, $listName) : false;
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
        $this->ensureInitialized();

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

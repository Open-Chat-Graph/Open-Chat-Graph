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

/**
 * おすすめ/カテゴリ/公式ランキングの静的データ(.dat)の読み書きを担う。
 *
 * - 読み（ページ表示）: get*Ranking() … .dat を読む。未生成（新規タグ等）/無効化時は、
 *   対象1件だけを DB から引く per-tag ビルダー(RecommendRankingBuilder)で即時生成する。
 * - 書き（毎時バッチ）: updateStaticData() … 全件を1回の fetchAll で読み、bulk ビルダーで
 *   一括生成して .dat 保存する（タグ数が多いので per-tag を都度引くより速い）。
 */
class RecommendStaticDataGenerator
{
    function __construct(
        private RecommendUpdater $recommendUpdater,
        private FileStorageInterface $fileStorage,
        private BulkRankingDataRepositoryInterface $bulkRankingDataRepository,
        private BulkRecommendRankingBuilderInterface $bulkRecommendRankingBuilder,
    ) {}

    // ============================================================
    // 読み（ページ表示）: .dat があれば読む、無ければ per-tag でDBから即時生成
    // ============================================================

    function getRecomendRanking(string $tag): RecommendListDto
    {
        return $this->fromFileOrDb(
            'recommendStaticDataDir',
            hash('crc32', $tag),
            fn() => app(RecommendRankingBuilder::class)->getRanking(
                RecommendListType::Tag,
                $tag,
                $tag,
                app(RecommendRankingRepository::class)
            )
        );
    }

    function getCategoryRanking(int $category): RecommendListDto
    {
        return $this->fromFileOrDb(
            'categoryStaticDataDir',
            (string)$category,
            fn() => app(RecommendRankingBuilder::class)->getRanking(
                RecommendListType::Category,
                (string)$category,
                getCategoryName($category),
                app(CategoryRankingRepository::class)
            )
        );
    }

    function getOfficialRanking(int $emblem): RecommendListDto
    {
        return $this->fromFileOrDb(
            'officialStaticDataDir',
            (string)$emblem,
            fn() => app(RecommendRankingBuilder::class)->getRanking(
                RecommendListType::Official,
                (string)$emblem,
                AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][$emblem] ?? '',
                app(OfficialRoomRankingRepository::class)
            )
        );
    }

    /**
     * 静的データ(.dat)を読む。無い/無効化時は $liveBuild() でDBから即時生成する。
     */
    private function fromFileOrDb(string $dirKey, string $fileName, callable $liveBuild): RecommendListDto
    {
        $data = $this->fileStorage->getSerializedFile(
            $this->fileStorage->getStorageFilePath($dirKey) . "/{$fileName}.dat"
        );

        if (!$data || AppConfig::$disableStaticDataFile) {
            return $liveBuild();
        }

        // 古い/空のキャッシュはキャッシュさせない
        if (
            !$data->getCount()
            || !$data->hourlyUpdatedAt === $this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime')
        ) {
            noStore();
        }

        return $data;
    }

    // ============================================================
    // 書き（毎時バッチ）: 全件を1回のfetchAllで読みbulkビルダーで一括生成
    // ============================================================

    /**
     * @return string[]
     */
    function getAllTagNames(): array
    {
        return $this->recommendUpdater->getAllTagNames();
    }

    function updateStaticData(): void
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

<?php

declare(strict_types=1);

namespace App\Services\Recommend\StaticData;

use App\Config\AppConfig;
use App\Models\RecommendRepositories\BulkRankingDataRepositoryInterface;
use App\Models\Repositories\Recommend\RecommendGrowthRepositoryInterface;
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
        private RecommendGrowthRepositoryInterface $recommendGrowthRepository,
    ) {}

    // ============================================================
    // 読み（ページ表示）: .dat があれば読む、無ければ per-tag でDBから即時生成
    // ============================================================

    function getRecomendRanking(string $tag): RecommendListDto
    {
        $dto = $this->fromFileOrDb(
            'recommendStaticDataDir',
            hash('crc32', $tag),
            fn() => app(RecommendRankingBuilder::class)->getRanking(
                RecommendListType::Tag,
                $tag,
                $tag,
                app(RecommendRankingRepository::class)
            )
        );

        // テーマの勢いは毎時バッチが .dat に同梱する。null（新規タグの即時生成・旧 .dat）の場合も
        // この層でライブ集計して埋め、呼び出し側へ null を漏らさない。
        // 「静的データが無ければその場で生成してフォールバックする」のは静的データ層の責務
        // （リスト本体の fromFileOrDb と同じ考え方）で、コントローラには持ち込まない。
        if ($dto->themeMomentum === null) {
            $this->setThemeMomentum($dto);
        }

        return $dto;
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

    /** @var array<string, RecommendListDto> リクエスト内メモ。/oc では同じタグの .dat を
     *  recommend(おすすめ枠)と SimilarSizeRoomService(人数絞り込み)が読むため、
     *  母集団300件化した .dat の unserialize を1回に抑える。 */
    private static array $memo = [];

    /**
     * 静的データ(.dat)を読む。無い/無効化時は $liveBuild() でDBから即時生成する。
     */
    private function fromFileOrDb(string $dirKey, string $fileName, callable $liveBuild): RecommendListDto
    {
        $memoKey = "{$dirKey}/{$fileName}";
        if (isset(self::$memo[$memoKey]) && !AppConfig::$disableStaticDataFile) {
            return self::$memo[$memoKey];
        }

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

        return self::$memo[$memoKey] = $data;
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
        // 関連タグマップは毎時バッチで直前(StaticDataGenerator::updateStaticData)に
        // 再生成済みのものを1回だけ読み、各タグの .dat に自タグ分のスライスを同梱する
        // (/recommend がアクセスごとに全タグ分のマップを展開するのを無くす)。
        // マップが無い環境(手動の部分実行等)では null のままにし、ページ側の
        // 従来読みフォールバックに任せる。
        $relatedTagsMap = $this->fileStorage->getSerializedFile('@relatedTags');
        if (!is_array($relatedTagsMap)) {
            $relatedTagsMap = null;
        }

        foreach ($this->getAllTagNames() as $tag) {
            $fileName = hash('crc32', $tag);
            $dto = $this->bulkRecommendRankingBuilder->buildTagRanking($tag, $tag);
            $this->setThemeMomentum($dto);
            if ($relatedTagsMap !== null) {
                $dto->relatedTags = $relatedTagsMap[$tag] ?? [];
            }
            $this->fileStorage->saveSerializedFile(
                $this->fileStorage->getStorageFilePath('recommendStaticDataDir') . "/{$fileName}.dat",
                $dto
            );
        }
    }

    /**
     * テーマの勢い(themeMomentum)を事前計算して DTO に同梱する。
     *
     * /recommend/{tag} がアクセスごとに ranking_position.db / statistics.db を集計していたのを、
     * 毎時の .dat 生成時の1回に寄せる。対象はタグ .dat のみ（勢いを表示するのはタグページだけ）。
     * 集計窓の起点・対象IDはページ側のライブ計算と同一
     * (RecommendOpenChatPageController::index と揃えること)。
     */
    private function setThemeMomentum(RecommendListDto $dto): void
    {
        if (!$dto->getCount()) {
            $dto->themeMomentum = [];
            return;
        }

        $dto->themeMomentum = $this->recommendGrowthRepository->themeMomentum(
            array_column($dto->getList(false, null), 'id'),
            new \DateTime($dto->hourlyUpdatedAt)
        );
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

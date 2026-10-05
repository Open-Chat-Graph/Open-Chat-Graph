<?php

declare(strict_types=1);

namespace App\Services\Recommend\StaticData;

use App\Config\AppConfig;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\RecommendRankingBuilder;
use App\Services\Recommend\RecommendUpdater;
use App\Services\Storage\FileStorageInterface;
use Shared\MimimalCmsConfig;

/**
 * おすすめ/カテゴリ/公式ランキングの静的データ(.dat)の読み書きを担う。
 *
 * - 読み（ページ表示）: get*Ranking() … .dat を読む。未生成（新規タグ等）/無効化時は、
 *   対象1件だけを DB から引く RecommendRankingBuilder で即時生成する。
 * - 書き（毎時バッチ）: updateStaticData() … タグを TAG_BULK_CHUNK_SIZE 件ずつ束ね、
 *   ウィンドウ関数の1クエリ(buildTagsBulk)で「タグごと上位POOL件」を一括取得して .dat を生成する。
 *   ページ表示時の未生成タグだけは従来どおり1件単位の buildTag() で即時生成する。
 */
class RecommendStaticDataGenerator
{
    /**
     * 毎時バッチでタグをまとめて取得する単位（1クエリあたりのタグ数）。
     * 大きいほどクエリ本数は減るが1クエリの結果セット(最大 POOL×CHUNK 行)とメモリが増える。
     */
    private const TAG_BULK_CHUNK_SIZE = 50;

    function __construct(
        private RecommendUpdater $recommendUpdater,
        private FileStorageInterface $fileStorage,
        private RecommendRankingBuilder $recommendRankingBuilder,
    ) {}

    // ============================================================
    // 読み（ページ表示）: .dat があれば読む、無ければDBから即時生成
    // ============================================================

    function getRecomendRanking(string $tag): RecommendListDto
    {
        return $this->fromFileOrDb(
            'recommendStaticDataDir',
            hash('crc32', $tag),
            fn() => $this->recommendRankingBuilder->buildTag($tag)
        );
    }

    function getCategoryRanking(int $category): RecommendListDto
    {
        return $this->fromFileOrDb(
            'categoryStaticDataDir',
            (string)$category,
            fn() => $this->recommendRankingBuilder->buildCategory($category, getCategoryName($category))
        );
    }

    function getOfficialRanking(int $emblem): RecommendListDto
    {
        return $this->fromFileOrDb(
            'officialStaticDataDir',
            (string)$emblem,
            fn() => $this->recommendRankingBuilder->buildOfficial(
                $emblem,
                AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][$emblem] ?? ''
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
        $this->updateRecommendStaticData();
        $this->updateCategoryStaticData();
        $this->updateOfficialStaticData();
    }

    private function updateRecommendStaticData(): void
    {
        // 関連タグマップは毎時バッチで直前(StaticDataGenerator::updateStaticData)に
        // 再生成済みのものを1回だけ読み、各タグの .dat に自タグ分のスライスを同梱する
        // (/recommend がアクセスごとに全タグ分のマップを展開するのを無くす)。
        $relatedTagsMap = $this->fileStorage->getSerializedFile('@relatedTags');
        if (!is_array($relatedTagsMap)) {
            $relatedTagsMap = [];
        }

        // タグを CHUNK 件ずつまとめて1クエリ(ウィンドウ関数)で取得する。
        // 旧実装は全タグ × buildTag() の N+1（重い JOIN をタグ数ぶん直列）で、本番(ja=650タグ)では
        // 毎時1時間以内に終わらず次回 cron に kill され続け一度も完走しなかった。チャンクバルク化で
        // クエリ本数を タグ数 → ceil(タグ数 / CHUNK) に圧縮する。
        foreach (array_chunk($this->getAllTagNames(), self::TAG_BULK_CHUNK_SIZE) as $tagChunk) {
            $dtoByTag = $this->recommendRankingBuilder->buildTagsBulk($tagChunk);

            foreach ($tagChunk as $tag) {
                // 念のためのフォールバック（バルクは全タグ分の DTO を返すため通常は通らない）。
                $dto = $dtoByTag[$tag] ?? $this->recommendRankingBuilder->buildTag($tag);
                $dto->relatedTags = $relatedTagsMap[$tag] ?? [];

                $fileName = hash('crc32', $tag);
                $this->fileStorage->saveSerializedFile(
                    $this->fileStorage->getStorageFilePath('recommendStaticDataDir') . "/{$fileName}.dat",
                    $dto
                );
            }
        }
    }

    private function updateCategoryStaticData(): void
    {
        foreach (AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] as $category) {
            $this->fileStorage->saveSerializedFile(
                $this->fileStorage->getStorageFilePath('categoryStaticDataDir') . "/{$category}.dat",
                $this->recommendRankingBuilder->buildCategory($category, getCategoryName($category))
            );
        }
    }

    private function updateOfficialStaticData(): void
    {
        foreach ([1, 2] as $emblem) {
            $listName = AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][$emblem] ?? '';
            if ($listName) {
                $this->fileStorage->saveSerializedFile(
                    $this->fileStorage->getStorageFilePath('officialStaticDataDir') . "/{$emblem}.dat",
                    $this->recommendRankingBuilder->buildOfficial($emblem, $listName)
                );
            }
        }
    }
}

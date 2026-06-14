<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Config\AppConfig;
use App\Models\RecommendRepositories\RecommendRankingRepository;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\Enum\RecommendListType;
use App\Services\Storage\FileStorageInterface;
use Shared\MimimalCmsConfig;

/**
 * tag/category/official のランキング DTO を1件ずつ構築する。
 *
 * 毎時バッチの .dat 一括生成(全エンティティを foreach)も、ページ表示時の未キャッシュ即時生成も
 * この同じ経路を通る（旧 bulk/per-tag の二重実装は廃止）。
 * リポジトリが返す「24h増→member→id」順の全行(最大 POOL 件)をそのまま DTO に渡し、
 * 表示30件は DTO 側が先頭から切り出す。
 */
class RecommendRankingBuilder
{
    private const SORT_AND_UNIQUE_ARRAY_MIN_COUNT = 5;

    public function __construct(
        private FileStorageInterface $fileStorage,
        private RecommendRankingRepository $repository,
    ) {}

    function buildTag(string $tag): RecommendListDto
    {
        return $this->build(
            RecommendListType::Tag,
            $tag,
            $this->repository->getRankingByTag($tag, AppConfig::LIST_LIMIT_RECOMMEND_POOL),
        );
    }

    function buildCategory(int $category, string $listName): RecommendListDto
    {
        return $this->build(
            RecommendListType::Category,
            $listName,
            $this->repository->getRankingByCategory($category, AppConfig::LIST_LIMIT_RECOMMEND_POOL),
        );
    }

    function buildOfficial(int $emblem, string $listName): RecommendListDto
    {
        return $this->build(
            RecommendListType::Official,
            $listName,
            $this->repository->getRankingByOfficial($emblem, AppConfig::LIST_LIMIT_RECOMMEND_POOL),
        );
    }

    /**
     * @param array<int, array<string,mixed>> $rows リポジトリが返したランキング順の生行
     */
    private function build(RecommendListType $type, string $listName, array $rows): RecommendListDto
    {
        $list = array_map(
            fn(array $row) => RecommendRowFormat::slim(
                $row,
                $row['table_name'],
                ((int)$row['diff_member_24h']) ?: null
            ),
            $rows
        );

        $dto = new RecommendListDto(
            $type,
            $listName,
            $list,
            $this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime')
        );

        // 日本以外では関連タグ(表示30件のtag1/tag2から共起の多いもの)を事前取得しておく
        if (MimimalCmsConfig::$urlRoot !== '') {
            $ids = array_column($dto->getList(false, null), 'id');
            $dto->sortAndUniqueTags = sortAndUniqueArray(
                array_merge($this->repository->getRecommendTags($ids), $this->repository->getOcTags($ids)),
                self::SORT_AND_UNIQUE_ARRAY_MIN_COUNT
            );
        }

        return $dto;
    }
}

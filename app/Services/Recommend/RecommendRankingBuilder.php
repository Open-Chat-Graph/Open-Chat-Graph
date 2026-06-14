<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Config\AppConfig;
use App\Models\RecommendRepositories\RecommendRankingRepository;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\Enum\RecommendListType;
use App\Services\Storage\FileStorageInterface;

/**
 * tag/category/official のランキング DTO を構築する。
 *
 * - 単発（buildTag/buildCategory/buildOfficial）: ページ表示時の未キャッシュ即時生成（1件）。
 * - バルク（buildTagsBulk）: 毎時バッチの .dat 一括生成。タグ N 件を1クエリでまとめて構築する。
 *
 * いずれもリポジトリが返す「24h増→member→id」順の全行(最大 POOL 件)をそのまま DTO に渡し、
 * 表示30件は DTO 側が先頭から切り出す。
 */
class RecommendRankingBuilder
{
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

    /**
     * 複数タグの DTO を「タグごとに1クエリ」ではなくバルク取得でまとめて構築する（毎時バッチ用）。
     * 返す DTO は単発 buildTag() と同一形式。呼び出し側（生成バッチ）はタグをチャンクで渡す。
     *
     * @param string[] $tags
     * @return array<string, RecommendListDto>
     */
    function buildTagsBulk(array $tags): array
    {
        $rowsByTag = $this->repository->getRankingByTagsBulk($tags, AppConfig::LIST_LIMIT_RECOMMEND_POOL);
        $hourlyUpdatedAt = $this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime');

        $result = [];
        foreach ($tags as $tag) {
            $result[$tag] = new RecommendListDto(
                RecommendListType::Tag,
                $tag,
                $this->slimRows($rowsByTag[$tag] ?? []),
                $hourlyUpdatedAt
            );
        }

        return $result;
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
        return new RecommendListDto(
            $type,
            $listName,
            $this->slimRows($rows),
            $this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime')
        );
    }

    /**
     * @param array<int, array<string,mixed>> $rows
     * @return array<int, array<string,mixed>>
     */
    private function slimRows(array $rows): array
    {
        return array_map(
            fn(array $row) => RecommendRowFormat::slim(
                $row,
                $row['table_name'],
                ((int)$row['diff_member_24h']) ?: null
            ),
            $rows
        );
    }
}

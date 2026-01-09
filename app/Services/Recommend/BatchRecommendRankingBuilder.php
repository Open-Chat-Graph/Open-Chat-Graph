<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Config\AppConfig;
use App\Models\RecommendRepositories\AbstractRecommendRankingRepository;
use App\Models\RecommendRepositories\BatchRecommendRankingRepository;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\Enum\RecommendListType;
use Shared\MimimalCmsConfig;

/**
 * 1タグ1クエリで全ランキングデータを取得する最適化版ビルダー
 */
class BatchRecommendRankingBuilder implements RecommendRankingBuilderInterface
{
    // 関連タグ取得に関する値（台湾・タイのみ）
    private const SORT_AND_UNIQUE_TAGS_LIST_LIMIT = null;
    private const SORT_AND_UNIQUE_ARRAY_MIN_COUNT = 5;

    function getRanking(
        RecommendListType $type,
        string $entity,
        string $listName,
        AbstractRecommendRankingRepository $repository
    ): RecommendListDto {
        // BatchRecommendRankingRepositoryの場合は最適化版を使用
        if ($repository instanceof BatchRecommendRankingRepository) {
            return $this->getRankingBatch($type, $entity, $listName, $repository);
        }

        // それ以外は従来の方法（互換性のため）
        return $this->getRankingLegacy($type, $entity, $listName, $repository);
    }

    /**
     * 1クエリで全ランキングを取得する最適化版
     */
    private function getRankingBatch(
        RecommendListType $type,
        string $entity,
        string $listName,
        BatchRecommendRankingRepository $repository
    ): RecommendListDto {
        $limit = AppConfig::LIST_LIMIT_RECOMMEND;

        // 1クエリで4つのランキングを取得
        // memberLimitは最大値で取得し、後でPHP側で調整
        $memberLimit = (int)floor(AppConfig::LIST_LIMIT_RECOMMEND);
        $rankings = $repository->getRankingBatch(
            $entity,
            AppConfig::RECOMMEND_MIN_MEMBER_DIFF_HOUR,
            AppConfig::RECOMMEND_MIN_MEMBER_DIFF_H24,
            AppConfig::RECOMMEND_MIN_MEMBER_DIFF_WEEK,
            $limit,
            $memberLimit
        );

        // 必要な件数だけに調整
        $actualMemberLimit = $this->calculateMemberLimit($rankings['hour'], $rankings['day'], $rankings['week']);
        $rankings['member'] = array_slice($rankings['member'], 0, $actualMemberLimit);

        $dto = new RecommendListDto(
            $type,
            $listName,
            $rankings['hour'],
            $rankings['day'],
            $rankings['week'],
            $rankings['member'],
            file_get_contents(AppConfig::getStorageFilePath('hourlyCronUpdatedAtDatetime'))
        );

        // 日本以外では関連タグを事前に取得しておく
        if (MimimalCmsConfig::$urlRoot !== '') {
            $list = array_column(
                $dto->getList(false, self::SORT_AND_UNIQUE_TAGS_LIST_LIMIT),
                'id'
            );

            $dto->sortAndUniqueTags = sortAndUniqueArray(
                array_merge($repository->getRecommendTags($list), $repository->getOcTags($list)),
                self::SORT_AND_UNIQUE_ARRAY_MIN_COUNT
            );
        }

        return $dto;
    }

    /**
     * 従来の複数クエリ方式（互換性のため）
     */
    private function getRankingLegacy(
        RecommendListType $type,
        string $entity,
        string $listName,
        AbstractRecommendRankingRepository $repository
    ): RecommendListDto {
        $limit = AppConfig::LIST_LIMIT_RECOMMEND;

        $ranking = $repository->getRanking(
            $entity,
            AppConfig::RANKING_HOUR_TABLE_NAME,
            AppConfig::RECOMMEND_MIN_MEMBER_DIFF_HOUR,
            $limit
        );

        $idArray = array_column($ranking, 'id');
        $ranking2 = $repository->getRankingByExceptId(
            $entity,
            AppConfig::RANKING_DAY_TABLE_NAME,
            AppConfig::RECOMMEND_MIN_MEMBER_DIFF_H24,
            $idArray,
            $limit
        );

        $count = count($ranking) + count($ranking2);
        $idArray = array_column(array_merge($ranking, $ranking2), 'id');
        $ranking3 = $repository->getRankingByExceptId(
            $entity,
            AppConfig::RANKING_WEEK_TABLE_NAME,
            AppConfig::RECOMMEND_MIN_MEMBER_DIFF_WEEK,
            $idArray,
            $limit
        );

        $count = count($ranking) + count($ranking2) + count($ranking3);
        $idArray = array_column(array_merge($ranking, $ranking2, $ranking3), 'id');
        $ranking4 = $repository->getListOrderByMemberDesc(
            $entity,
            $idArray,
            $count < AppConfig::LIST_LIMIT_RECOMMEND ? ($count < floor(AppConfig::LIST_LIMIT_RECOMMEND) ? (int)floor(AppConfig::LIST_LIMIT_RECOMMEND) - $count : 5) : 3
        );

        $dto = new RecommendListDto(
            $type,
            $listName,
            $ranking,
            $ranking2,
            $ranking3,
            $ranking4,
            file_get_contents(AppConfig::getStorageFilePath('hourlyCronUpdatedAtDatetime'))
        );

        // 日本以外では関連タグを事前に取得しておく
        if (MimimalCmsConfig::$urlRoot !== '') {
            $list = array_column(
                $dto->getList(false, self::SORT_AND_UNIQUE_TAGS_LIST_LIMIT),
                'id'
            );

            $dto->sortAndUniqueTags = sortAndUniqueArray(
                array_merge($repository->getRecommendTags($list), $repository->getOcTags($list)),
                self::SORT_AND_UNIQUE_ARRAY_MIN_COUNT
            );
        }

        return $dto;
    }

    /**
     * memberランキングの取得件数を計算
     */
    private function calculateMemberLimit(array $ranking1, array $ranking2, array $ranking3): int
    {
        $count = count($ranking1) + count($ranking2) + count($ranking3);
        if ($count < AppConfig::LIST_LIMIT_RECOMMEND) {
            if ($count < floor(AppConfig::LIST_LIMIT_RECOMMEND)) {
                return (int)floor(AppConfig::LIST_LIMIT_RECOMMEND) - $count;
            }
            return 5;
        }
        return 3;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Config\AppConfig;
use App\Models\RecommendRepositories\AbstractRecommendRankingRepository;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\Enum\RecommendListType;
use Shared\MimimalCmsConfig;

class RecommendRankingBuilder
{
    // 関連タグを取得する際のリスト件数上限（台湾・タイのみ）
    private const SORT_AND_UNIQUE_TAGS_LIST_LIMIT = 20;

    function getRanking(
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

        if (MimimalCmsConfig::$urlRoot === '/tw' || MimimalCmsConfig::$urlRoot === '/th') {
            $list = $dto->getList(false, self::SORT_AND_UNIQUE_TAGS_LIST_LIMIT);
            $dto->sortAndUniqueTags = sortAndUniqueArray(
                array_merge(
                    $repository->getRecommendTags(array_column($list, 'id')),
                    $repository->getOcTags(array_column($list, 'id'))
                ),
                1
            );
        }

        return $dto;
    }
}

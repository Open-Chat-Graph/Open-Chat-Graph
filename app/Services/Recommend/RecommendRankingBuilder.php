<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Config\AppConfig;
use App\Models\RecommendRepositories\RecommendRankingRepositoryInterface;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\Enum\RecommendListType;

class RecommendRankingBuilder
{
    function getRanking(
        RecommendListType $type,
        string $entity,
        string $listName,
        RecommendRankingRepositoryInterface $repository
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

        return $dto;
    }
}

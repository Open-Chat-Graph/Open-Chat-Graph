<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Config\AppConfig;
use App\Models\RecommendRepositories\AbstractRecommendRankingRepository;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\Enum\RecommendListType;
use App\Services\Storage\FileStorageInterface;
use Shared\MimimalCmsConfig;

class RecommendRankingBuilder
{
    // 関連タグ取得に関する値（台湾・タイのみ）
    private const SORT_AND_UNIQUE_TAGS_LIST_LIMIT = null;
    private const SORT_AND_UNIQUE_ARRAY_MIN_COUNT = 5;

    public function __construct(
        private FileStorageInterface $fileStorage
    ) {}

    function getRanking(
        RecommendListType $type,
        string $entity,
        string $listName,
        AbstractRecommendRankingRepository $repository
    ): RecommendListDto {
        $limit = AppConfig::LIST_LIMIT_RECOMMEND;

        // 24時間の人数増加が大きい順（＝「いま伸びている」部屋）。
        $growing = $repository->getRanking(
            $entity,
            AppConfig::RANKING_DAY_TABLE_NAME,
            AppConfig::RECOMMEND_MIN_MEMBER_DIFF_H24,
            $limit
        );

        // 伸びていない部屋は member 降順で裾を埋める（痩せタグ対策・既存の大型部屋）。
        // 表示は30件のままだが、/oc 関連ルームの人数絞り込み母集団として300件保持する。
        $member = $repository->getListOrderByMemberDesc(
            $entity,
            array_column($growing, 'id'),
            AppConfig::LIST_LIMIT_RECOMMEND_POOL
        );

        // DTO は先頭(=表示順)に伸び部屋、末尾に裾を渡す（旧 hour/day/week の4段は廃止）。
        $dto = new RecommendListDto(
            $type,
            $listName,
            $growing,
            [],
            [],
            $member,
            $this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime')
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
}

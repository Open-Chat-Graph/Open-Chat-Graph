<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Models\Repositories\RankingPosition\RankingPositionOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsOhlcRepositoryInterface;
use App\Services\OpenChat\Enum\RankingType;

/**
 * /oc/{id} narrative セクション用のデータ集約 Repository。
 *
 * - statistics_ohlc 由来のメンバー数メトリクス
 * - ranking_position_ohlc 由来のカテゴリ内順位推移
 * を 1 ヶ所で取得し、Service 層を時系列クエリの詳細から切り離す。
 *
 * 既存 OHLC Repository を再利用し、生 SQL は呼ばない（テストしやすさ重視）。
 */
class OcNarrativeRepository implements OcNarrativeRepositoryInterface
{
    public function __construct(
        private StatisticsOhlcRepositoryInterface $statisticsOhlcRepository,
        private RankingPositionOhlcRepositoryInterface $rankingPositionOhlcRepository,
    ) {
    }

    public function getMemberMetrics(int $openChatId): array
    {
        return $this->statisticsOhlcRepository->getMemberMetricsForNarrative($openChatId);
    }

    public function getPositionMovement(int $openChatId, int $category, int $days = 30): array
    {
        return $this->rankingPositionOhlcRepository->getRecentPositionMovement(
            $openChatId,
            $category,
            RankingType::Ranking,
            $days
        );
    }
}

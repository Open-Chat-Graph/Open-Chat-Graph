<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Models\Repositories\RankingPosition\RankingPositionOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsOhlcRepositoryInterface;
use App\Models\SQLite\SQLiteOcgraphSqlapi;
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

    public function getAveragePosition(int $openChatId, int $category, string $type, int $days = 30): array
    {
        $rankingType = $type === 'rising' ? RankingType::Rising : RankingType::Ranking;
        return $this->rankingPositionOhlcRepository->getAveragePosition(
            $openChatId,
            $category,
            $rankingType,
            $days
        );
    }

    public function getGrowthRankingPositions(int $openChatId): array
    {
        $query =
            "SELECT
                (SELECT ranking_position FROM growth_ranking_past_hour     WHERE openchat_id = :id) AS hour_pos,
                (SELECT ranking_position FROM growth_ranking_past_24_hours WHERE openchat_id = :id) AS day_pos,
                (SELECT ranking_position FROM growth_ranking_past_week     WHERE openchat_id = :id) AS week_pos";

        try {
            SQLiteOcgraphSqlapi::connect(['mode' => '?mode=ro']);
            $row = SQLiteOcgraphSqlapi::fetch($query, ['id' => $openChatId]);
            SQLiteOcgraphSqlapi::$pdo = null;
        } catch (\Throwable $e) {
            return ['hour' => null, 'day' => null, 'week' => null];
        }

        return [
            'hour' => $row && $row['hour_pos'] !== null ? (int)$row['hour_pos'] : null,
            'day'  => $row && $row['day_pos']  !== null ? (int)$row['day_pos']  : null,
            'week' => $row && $row['week_pos'] !== null ? (int)$row['week_pos'] : null,
        ];
    }
}

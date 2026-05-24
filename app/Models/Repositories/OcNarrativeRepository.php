<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Models\Repositories\RankingPosition\RankingPositionOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;
use App\Models\SQLite\SQLiteOcgraphSqlapi;
use App\Services\OpenChat\Enum\RankingType;

/**
 * /oc/{id} narrative セクション用のデータ集約 Repository。
 *
 * - メンバー数メトリクス: daily の statistics テーブル (欠損なし)
 *   ※ statistics_ohlc / ranking 系はランキング掲載日のみ記録され欠損が出るため、
 *     メンバー数の時系列には使わない (仕様上ランキング外の期間が抜ける)
 * - 順位 / 急上昇: ranking_position_ohlc (欠損は仕様通り、ラベルが出ないだけ)
 *
 * Service 層を時系列クエリの詳細から切り離す。
 */
class OcNarrativeRepository implements OcNarrativeRepositoryInterface
{
    public function __construct(
        private StatisticsRepositoryInterface $statisticsRepository,
        private RankingPositionOhlcRepositoryInterface $rankingPositionOhlcRepository,
    ) {
    }

    public function getMemberMetrics(int $openChatId): array
    {
        return $this->statisticsRepository->getMemberMetricsForNarrative($openChatId);
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

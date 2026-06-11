<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Models\Repositories\DB;
use App\Models\Repositories\RankingPosition\RankingPositionOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;
use App\Services\OpenChat\Enum\RankingType;

/**
 * /oc/{id} narrative セクション用のデータ集約 Repository。
 *
 * - メンバー数メトリクス: daily の statistics テーブル (欠損なし)
 *   ※ statistics_ohlc / ranking 系はランキング掲載日のみ記録され欠損が出るため、
 *     メンバー数の時系列には使わない (仕様上ランキング外の期間が抜ける)
 * - 順位 / 急上昇: ranking_position_ohlc (欠損は仕様通り、ラベルが出ないだけ)
 * - 成長ランキング位置: MySQL の statistics_ranking_* (毎時洗い替え、id がそのまま順位)
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

    public function getMemberMetrics(int $openChatId, ?string $baseDate = null): array
    {
        return $this->statisticsRepository->getMemberMetricsForNarrative($openChatId, $baseDate);
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
        // statistics_ranking_* は毎時洗い替えされ、id (PK) がそのまま順位 (1 始まり)
        $query =
            "SELECT
                (SELECT id FROM statistics_ranking_hour   WHERE open_chat_id = :id1) AS hour_pos,
                (SELECT id FROM statistics_ranking_hour24 WHERE open_chat_id = :id2) AS day_pos,
                (SELECT id FROM statistics_ranking_week   WHERE open_chat_id = :id3) AS week_pos";

        $row = DB::fetch($query, ['id1' => $openChatId, 'id2' => $openChatId, 'id3' => $openChatId]);

        return [
            'hour' => $row && $row['hour_pos'] !== null ? (int)$row['hour_pos'] : null,
            'day'  => $row && $row['day_pos']  !== null ? (int)$row['day_pos']  : null,
            'week' => $row && $row['week_pos'] !== null ? (int)$row['week_pos'] : null,
        ];
    }
}

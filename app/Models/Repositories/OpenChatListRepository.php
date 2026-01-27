<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Models\Repositories\DB;

class OpenChatListRepository implements OpenChatListRepositoryInterface, OpenChatRecentListRepositoryInterface
{
    public function getRecordCount(): int
    {
        return (int)DB::execute(
            'SELECT COUNT(*) FROM open_chat'
        )->fetchColumn();
    }

    public function getDailyRankingRecordCount(): int
    {
        return (int)DB::execute(
            'SELECT COUNT(id) FROM statistics_ranking_day'
        )->fetchColumn();
    }

    public function getPastWeekRankingRecordCount(): int
    {
        return (int)DB::execute(
            'SELECT COUNT(id) FROM statistics_ranking_week'
        )->fetchColumn();
    }

    public function findAllOrderByEntity(
        int $startId,
        int $endId,
    ): array {
        $query =
            'SELECT
                id,
                name,
                emid,
                img_url,
                description,
                member,
                category,
                join_method_type,
                created_at AS datetime
            FROM
                open_chat
            WHERE
                api_created_at IS NOT NULL
            ORDER BY
                id ASC
            LIMIT
                :startId, :limit';

        $limit = $endId - $startId;
        return DB::fetchAll($query, compact('startId', 'limit'));
    }

    public function findAllOrderByIdCreatedAtColumn(): array
    {
        $date = date('Y-m-d');

        $query =
            "SELECT
                CASE
                    WHEN YEAR(:date) = YEAR(`created_at`)
                    THEN DATE_FORMAT(`created_at`, '%m/%d %H時')
                    ELSE DATE_FORMAT(`created_at`, '%Y/%m/%d %H時')
                END AS `created_at`
            FROM
                open_chat
            WHERE
                api_created_at IS NOT NULL
            ORDER BY
                id ASC";

        return DB::fetchAll($query, compact('date'), args: [\PDO::FETCH_COLUMN]);
    }

    public function getRankingRecordByMylist(array $idArray): array
    {
        $statements = array_fill(0, count($idArray), "?");
        $statement = "(" . implode(',', $statements) . ")";

        $query =
            "SELECT
                oc.id,
                oc.name,
                oc.emid,
                oc.img_url,
                oc.description,
                oc.member,
                oc.emblem,
                oc.join_method_type,
                ranking.diff_member AS diff_member,
                ranking.percent_increase AS percent_increase,
                ranking.id AS ranking_id
            FROM
                open_chat AS oc
                LEFT JOIN statistics_ranking_hour24 AS ranking ON oc.id = ranking.open_chat_id
            WHERE
                oc.id IN {$statement}";

        DB::connect();
        $stmt = DB::$pdo->prepare($query);
        $stmt->execute($idArray);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findMemberStatsHourlyRanking(
        int $startId,
        int $endId,
    ): array {
        return $this->findMemberStatsRanking($startId, $endId, 'statistics_ranking_hour');
    }

    public function findMemberStatsDailyRanking(
        int $startId,
        int $endId,
    ): array {
        return $this->findMemberStatsRanking($startId, $endId, 'statistics_ranking_hour24');
    }

    public function findMemberStatsPastWeekRanking(
        int $startId,
        int $endId,
    ): array {
        return $this->findMemberStatsRanking($startId, $endId, 'statistics_ranking_week');
    }

    protected function findMemberStatsRanking(
        int $startId,
        int $endId,
        string $tableName
    ): array {
        $query =
            "SELECT
                oc.id,
                oc.name,
                oc.emid,
                oc.img_url,
                oc.description,
                oc.member,
                oc.emblem,
                oc.category,
                oc.join_method_type,
                ranking.diff_member,
                ranking.percent_increase
            FROM
                open_chat AS oc
                JOIN (
                    SELECT
                        *
                    FROM
                        {$tableName}
                    WHERE
                        id > :startId
                        AND id <= :endId
                ) AS ranking ON oc.id = ranking.open_chat_id
            ORDER BY
                ranking.diff_member DESC";

        return DB::fetchAll($query, compact('startId', 'endId'));
    }

    function findMemberCountRanking(int $limit, array $whereIdNotIn): array
    {
        $excludeId = $whereIdNotIn ? implode(",", $whereIdNotIn) : '0';

        $query =
            "SELECT
                id,
                name,
                emid,
                img_url,
                description,
                member,
                emblem,
                category,
                join_method_type,
                api_created_at
            FROM
                open_chat
            WHERE 
                id NOT IN({$excludeId}) 
            ORDER BY
                member DESC
            LIMIT
                :limit";

        return DB::fetchAll($query, compact('limit'));
    }

    /**
     * @return array{ id: int, updated_at: string }[]
     */
    public function getOpenChatSiteMapData(): array
    {
        return DB::fetchAll("SELECT id, updated_at FROM open_chat ORDER BY id ASC");
    }
}

<?php

declare(strict_types=1);

namespace App\Models\RankingBanRepositories;

use App\Models\Repositories\DB;

class RankingBanPageRepository
{
    /**
     * @param int $publish 0:掲載中のみ, 1:未掲載のみ, 2:すべて
     * @param int $change 0:内容変更ありのみ, 1:変更なしのみ, 2:すべて
     */
    public function findAllOrderByIdDesc(
        int $change,
        int $publish,
        int $percent,
        string $keyword,
        int $offset,
        int $limit,
    ): array {
        $whereClause = $this->buildWhereClause($change, $publish, $percent);

        $query = fn ($like) =>
        "SELECT
            oc.id,
            oc.name,
            oc.description,
            oc.img_url,
            oc.emblem,
            oc.join_method_type,
            oc.category,
            oc.member,
            rb.member AS old_member,
            rb.datetime AS old_datetime,
            rb.end_datetime AS end_datetime,
            rb.percentage,
            rb.flag,
            rb.updated_at,
            rb.update_items
        FROM
            ranking_ban AS rb
            JOIN open_chat AS oc ON oc.id = rb.open_chat_id
        WHERE
            {$whereClause} {$like}
        ORDER BY
            IFNULL(GREATEST(rb.datetime, rb.end_datetime), rb.datetime) DESC,
            oc.member DESC
        LIMIT
            :offset, :limit";

        if ($keyword !== '') {
            return DB::executeLikeSearchQuery(
                $query,
                fn ($i) => "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i})",
                $keyword,
                compact('offset', 'limit'),
                whereClausePrefix: 'AND '
            );
        } else {
            return DB::fetchAll($query(''), compact('offset', 'limit'));
        }
    }

    /**
     * @param int $publish 0:掲載中のみ, 1:未掲載のみ, 2:すべて
     * @param int $change 0:内容変更ありのみ, 1:変更なしのみ, 2:すべて
     */
    public function findAllDatetimeColumn(int $change, int $publish, int $percent, string $keyword): array
    {
        $whereClause = $this->buildWhereClause($change, $publish, $percent);

        $query = fn ($like) =>
        "SELECT
            IFNULL(GREATEST(rb.datetime, rb.end_datetime), rb.datetime) AS `datetime`
        FROM
            ranking_ban AS rb
            JOIN open_chat AS oc ON oc.id = rb.open_chat_id
        WHERE
            {$whereClause} {$like}
        ORDER BY
            IFNULL(GREATEST(rb.datetime, rb.end_datetime), rb.datetime) DESC,
            rb.datetime DESC,
            percentage ASC";

        if ($keyword !== '') {
            return DB::executeLikeSearchQuery(
                $query,
                fn ($i) => "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i})",
                $keyword,
                fetchAllArgs: [\PDO::FETCH_COLUMN, 0],
                whereClausePrefix: 'AND '
            );
        } else {
            return DB::fetchAll($query(''), args: [\PDO::FETCH_COLUMN, 0]);
        }
    }

    /**
     * @param int $change 0:内容変更ありのみ, 1:変更なしのみ, 2:すべて
     * @param int $publish 0:掲載中のみ, 1:未掲載のみ, 2:すべて
     */
    private function buildWhereClause(int $change, int $publish, int $percent)
    {
        $updatedAtValue = $change === 0
            ? "AND (rb.updated_at >= 1 OR (rb.update_items IS NOT NULL AND rb.update_items != ''))"
            : ($change === 1 ? "AND (rb.updated_at = 0 AND (rb.update_items IS NULL OR rb.update_items = '') AND rb.datetime >= '2025-08-10 23:59:59')" : '');

        $endDatetime = $publish === 0
            ? "AND rb.end_datetime IS NOT NULL"
            : ($publish === 1 ? "AND rb.end_datetime IS NULL" : '');

        $member = $percent < 100
            ? ($percent < 80 ? 'AND rb.member >= 30' : 'AND rb.member >= 10')
            : '';

        return "rb.percentage <= {$percent} {$updatedAtValue} {$endDatetime} {$member}";
    }
}

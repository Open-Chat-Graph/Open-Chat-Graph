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
        string $since = '',
        string $until = '',
        int $dmin = 0,
        int $dmax = 0,
        string $now = '',
        array $items = [],
    ): array {
        $whereClause = $this->buildWhereClause($change, $publish, $percent)
            . $this->buildDateClause($since, $until, $dateParams)
            . $this->buildDurationClause($dmin, $dmax, $now, $durationParams)
            . $this->buildItemsClause($items, $itemsParams);

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
                compact('offset', 'limit') + $dateParams + $durationParams + $itemsParams,
                whereClausePrefix: 'AND '
            );
        } else {
            return DB::fetchAll($query(''), compact('offset', 'limit') + $dateParams + $durationParams + $itemsParams);
        }
    }

    /**
     * @param int $publish 0:掲載中のみ, 1:未掲載のみ, 2:すべて
     * @param int $change 0:内容変更ありのみ, 1:変更なしのみ, 2:すべて
     */
    public function findAllDatetimeColumn(int $change, int $publish, int $percent, string $keyword, string $since = '', string $until = '', int $dmin = 0, int $dmax = 0, string $now = '', array $items = []): array
    {
        $whereClause = $this->buildWhereClause($change, $publish, $percent)
            . $this->buildDateClause($since, $until, $dateParams)
            . $this->buildDurationClause($dmin, $dmax, $now, $durationParams)
            . $this->buildItemsClause($items, $itemsParams);

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
                ($dateParams + $durationParams + $itemsParams) ?: null,
                fetchAllArgs: [\PDO::FETCH_COLUMN, 0],
                whereClausePrefix: 'AND '
            );
        } else {
            return DB::fetchAll($query(''), ($dateParams + $durationParams + $itemsParams) ?: null, args: [\PDO::FETCH_COLUMN, 0]);
        }
    }

    /**
     * 「消えた日時(rb.datetime)」の期間絞り込み。値はバインドパラメータで渡す（連結しない）。
     *
     * @param string $since YYYY-MM-DD（検証済み・空文字なら条件なし）
     * @param string $until YYYY-MM-DD（同上）
     * @param array|null $dateParams バインド用パラメータの出力先
     */
    private function buildDateClause(string $since, string $until, ?array &$dateParams): string
    {
        $clause = '';
        $dateParams = [];
        if ($since !== '') {
            $clause .= ' AND rb.datetime >= :since';
            $dateParams['since'] = $since . ' 00:00:00';
        }
        if ($until !== '') {
            $clause .= ' AND rb.datetime <= :until';
            $dateParams['until'] = $until . ' 23:59:59';
        }
        return $clause;
    }

    /**
     * 「消えていた期間」の絞り込み。復活済み（end_datetime あり）は復活までにかかった時間、
     * 未掲載中（end_datetime なし）は基準時刻 $now（毎時クロールの最新時刻）までの経過時間。
     * いずれも時間単位で、dmin は「超」(>)・dmax は「以下」(<=)。
     * 変更後の復活は「ちょうど24時間」(TIMESTAMPDIFF=24) に集中するため、dmax=24（24時間以内）が
     * 典型の24時間戻りを含み、dmin=24（1〜3日）はそれを除く——この境界で直感と一致させる。
     * 値はバインドパラメータで渡す（連結しない）。
     *
     * @param int $dmin 下限（時間・この値を超える）。0なら条件なし
     * @param int $dmax 上限（時間・この値以下）。0なら条件なし
     * @param string $now 基準時刻 'Y-m-d H:i:s'
     * @param array|null $durationParams バインド用パラメータの出力先
     */
    private function buildDurationClause(int $dmin, int $dmax, string $now, ?array &$durationParams): string
    {
        $durationParams = [];
        if (($dmin <= 0 && $dmax <= 0) || $now === '') return '';

        $clause = '';
        if ($dmin > 0) {
            $clause .= ' AND TIMESTAMPDIFF(HOUR, rb.datetime, COALESCE(rb.end_datetime, :dminNow)) > :dmin';
            $durationParams += ['dminNow' => $now, 'dmin' => $dmin];
        }
        if ($dmax > 0) {
            $clause .= ' AND TIMESTAMPDIFF(HOUR, rb.datetime, COALESCE(rb.end_datetime, :dmaxNow)) <= :dmax';
            $durationParams += ['dmaxNow' => $now, 'dmax' => $dmax];
        }
        return $clause;
    }

    /**
     * 「ルームの変更内容」の絞り込み。選んだキーを「すべて変更した」部屋だけに絞る（AND）。
     * update_items は {"name":false,...,"emblem":true} 形式の JSON 文字列なので、
     * 該当キーが `"key":true` を含むかで判定する。キーは呼び出し側で検証済みだが、念のため二重にホワイトリストする。
     * 値はバインドパラメータで渡す（連結しない）。
     *
     * @param list<string> $items 変更内容キー（空配列なら条件なし）
     * @param array|null $itemsParams バインド用パラメータの出力先
     */
    private function buildItemsClause(array $items, ?array &$itemsParams): string
    {
        $itemsParams = [];
        $allowed = ['name', 'description', 'img_url', 'join_method_type', 'category', 'emblem'];

        $clause = '';
        $i = 0;
        foreach ($items as $key) {
            if (!in_array($key, $allowed, true)) continue;
            $clause .= " AND rb.update_items LIKE :item{$i}";
            $itemsParams["item{$i}"] = '%"' . $key . '":true%';
            $i++;
        }
        return $clause;
    }

    /**
     * @param int $change 0:内容変更ありのみ, 1:変更なしのみ, 2:すべて
     * @param int $publish 0:掲載中のみ, 1:未掲載のみ, 2:すべて
     */
    private function buildWhereClause(int $change, int $publish, int $percent): string
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

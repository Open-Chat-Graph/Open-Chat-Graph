<?php

declare(strict_types=1);

namespace App\Models\RankingBanRepositories;

use App\Models\Repositories\DB;

class RankingBanPageRepository
{
    /** ソートキー生成列 sort_datetime の索引が存在するか（ワーカー単位で1度だけ確認しキャッシュ） */
    private static ?bool $hasSortIndex = null;

    /**
     * 重い ORDER BY（IFNULL(GREATEST(datetime, end_datetime), datetime)）を索引で満たすための
     * 生成列＋索引 idx_rb_sortdt が存在するか。存在すれば高速パス（FORCE INDEX で filesort 回避）を、
     * 無ければ従来の式 ORDER BY（filesort）にフォールバックする。
     * これによりデプロイ中（列追加済み・索引未構築）や索引構築失敗時でもクエリは壊れない（退行のみ）。
     */
    private function hasSortDatetimeIndex(): bool
    {
        if (self::$hasSortIndex !== null) return self::$hasSortIndex;
        try {
            $stmt = DB::execute(
                "SELECT 1 FROM information_schema.STATISTICS"
                    . " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ranking_ban' AND INDEX_NAME = 'idx_rb_sortdt' LIMIT 1"
            );
            return self::$hasSortIndex = (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return self::$hasSortIndex = false; // 確認できないときは安全側（従来パス）
        }
    }

    /**
     * 生成列の索引(sort_datetime, member)で並べ替える高速パスを使えるか。
     *
     * この索引順の走査＋早期終了（LIMIT で打ち切り）は最悪でも索引を1周（約3.4秒・実測）で頭打ち。
     * percentage/期間/変更内容などで絞り込んでも、一致が多い限り LIMIT で早く止まり 0.1〜1.6秒に収まる。
     * よって従来の全件 filesort（順位%や期間指定で 17〜19秒）より常に速い —— ただし1つだけ例外がある。
     *
     * 例外＝日付範囲(since/until)。古い狭い窓に一致が少数だけ散在すると、LIMIT に達せず索引末尾まで
     * 空振り走査してしまい、小さくなった集合を filesort する従来パス(約1.3秒)より遅くなる(約3.4秒)。
     * そのため日付範囲が指定されたときだけ従来パスに任せる。
     *
     * キーワード検索は別経路(LIKE)・publish 1(未掲載のみ＝小集合で既存索引が速い)も対象外。
     */
    private function useSortDatetimeFastPath(int $publish, string $keyword, string $since, string $until): bool
    {
        return $keyword === ''
            && $since === '' && $until === ''
            && ($publish === 0 || $publish === 2)
            && $this->hasSortDatetimeIndex();
    }

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

        // 高速パス: 生成列 sort_datetime の索引で並べ替える（filesort を避け、LIMIT で早期終了）。
        // rb を先頭駆動にするため STRAIGHT_JOIN、ソート索引を確実に使うため FORCE INDEX を付ける。
        $fast = $this->useSortDatetimeFastPath($publish, $keyword, $since, $until);
        $straightJoin = $fast ? 'STRAIGHT_JOIN' : '';
        $forceIndex = $fast ? 'FORCE INDEX (idx_rb_sortdt)' : '';
        $orderBy = $fast
            ? 'rb.sort_datetime DESC, rb.member DESC, rb.id DESC'
            : 'IFNULL(GREATEST(rb.datetime, rb.end_datetime), rb.datetime) DESC, oc.member DESC';

        $query = fn ($like) =>
        "SELECT {$straightJoin}
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
            ranking_ban AS rb {$forceIndex}
            JOIN open_chat AS oc ON oc.id = rb.open_chat_id
        WHERE
            {$whereClause} {$like}
        ORDER BY
            {$orderBy}
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
     * ページャのラベル用に「消えた日時(sort_datetime)」を新しい順で最大 $limit 件返す。
     * 表示できるページ数には上限があるため全件は不要で、$limit で頭打ちにして filesort/転送を抑える。
     *
     * @param int $publish 0:掲載中のみ, 1:未掲載のみ, 2:すべて
     * @param int $change 0:内容変更ありのみ, 1:変更なしのみ, 2:すべて
     * @param int $limit 取得上限（表示上限ページ×1ページ件数＋1）
     */
    public function findAllDatetimeColumn(int $change, int $publish, int $percent, string $keyword, int $limit, string $since = '', string $until = '', int $dmin = 0, int $dmax = 0, string $now = '', array $items = []): array
    {
        $whereClause = $this->buildWhereClause($change, $publish, $percent)
            . $this->buildDateClause($since, $until, $dateParams)
            . $this->buildDurationClause($dmin, $dmax, $now, $durationParams)
            . $this->buildItemsClause($items, $itemsParams);

        $fast = $this->useSortDatetimeFastPath($publish, $keyword, $since, $until);
        $straightJoin = $fast ? 'STRAIGHT_JOIN' : '';
        $forceIndex = $fast ? 'FORCE INDEX (idx_rb_sortdt)' : '';
        $sortExpr = $fast ? 'rb.sort_datetime' : 'IFNULL(GREATEST(rb.datetime, rb.end_datetime), rb.datetime)';
        $orderBy = $fast
            ? 'rb.sort_datetime DESC, rb.member DESC, rb.id DESC'
            : 'IFNULL(GREATEST(rb.datetime, rb.end_datetime), rb.datetime) DESC, rb.datetime DESC, percentage ASC';

        $query = fn ($like) =>
        "SELECT {$straightJoin}
            {$sortExpr} AS `datetime`
        FROM
            ranking_ban AS rb {$forceIndex}
            JOIN open_chat AS oc ON oc.id = rb.open_chat_id
        WHERE
            {$whereClause} {$like}
        ORDER BY
            {$orderBy}
        LIMIT :limit";

        if ($keyword !== '') {
            return DB::executeLikeSearchQuery(
                $query,
                fn ($i) => "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i})",
                $keyword,
                ['limit' => $limit] + $dateParams + $durationParams + $itemsParams,
                fetchAllArgs: [\PDO::FETCH_COLUMN, 0],
                whereClausePrefix: 'AND '
            );
        } else {
            return DB::fetchAll($query(''), ['limit' => $limit] + $dateParams + $durationParams + $itemsParams, args: [\PDO::FETCH_COLUMN, 0]);
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

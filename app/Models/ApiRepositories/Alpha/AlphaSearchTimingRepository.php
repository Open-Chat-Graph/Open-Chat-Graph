<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories\Alpha;

use App\Models\UserLogRepositories\UserLogDB;

/**
 * Alpha 検索ETA（プログレスバー用）リポジトリ。
 *
 * /alpha-api/search が実処理の wall time を query_key 単位で upsert し、
 * /alpha-api/search-eta が「次に同条件で検索したら何ms くらいかかるか」を返すために読む。
 *
 * query_key は keyword|category|sort|order を正規化した文字列（呼び出し側で組み立て）。
 * すべて ocgraph_userlog DB（UserLogDB）に対して行う。追加のみ・既存破壊なし。ja 専用。
 */
class AlphaSearchTimingRepository
{
    /** ETA がまだ1件も無いときに返す既定値（ms） */
    public const DEFAULT_ETA_MS = 800;

    /**
     * query_key の検索処理時間を記録（upsert）。
     */
    public function record(string $queryKey, int $elapsedMs): void
    {
        if ($queryKey === '') {
            return;
        }
        $elapsedMs = max(0, $elapsedMs);
        UserLogDB::execute(
            "INSERT INTO alpha_search_timing (query_key, elapsed_ms)
             VALUES (:k, :ms)
             ON DUPLICATE KEY UPDATE elapsed_ms = VALUES(elapsed_ms),
                                     updated_at = current_timestamp()",
            ['k' => $queryKey, 'ms' => $elapsedMs]
        );
    }

    /**
     * query_key に対応する直近の elapsed_ms を返す。無ければ null。
     */
    public function getElapsedMs(string $queryKey): ?int
    {
        if ($queryKey === '') {
            return null;
        }
        $v = UserLogDB::fetchColumn(
            "SELECT elapsed_ms FROM alpha_search_timing WHERE query_key = :k",
            ['k' => $queryKey]
        );
        return ($v === false || $v === null) ? null : (int)$v;
    }

    /**
     * ETA を解決する。
     *   1. 該当 query_key の elapsed_ms があればそれ
     *   2. 無ければ全体の中央値（無ければ平均）
     *   3. それも無ければ DEFAULT_ETA_MS
     */
    public function resolveEtaMs(string $queryKey): int
    {
        $exact = $this->getElapsedMs($queryKey);
        if ($exact !== null) {
            return $exact;
        }

        $rows = UserLogDB::fetchAll("SELECT elapsed_ms FROM alpha_search_timing");
        if (empty($rows)) {
            return self::DEFAULT_ETA_MS;
        }

        $values = array_map(static fn($r) => (int)$r['elapsed_ms'], $rows);
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);
        if ($n % 2 === 1) {
            return $values[$mid];
        }
        // 偶数個は中央2値の平均（中央値）
        return (int)round(($values[$mid - 1] + $values[$mid]) / 2);
    }
}

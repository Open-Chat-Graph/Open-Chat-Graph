<?php

declare(strict_types=1);

namespace App\Models\SQLite\Repositories\Analysis;

use App\Models\Repositories\Analysis\AnalysisStatsRepositoryInterface;
use App\Models\SQLite\SQLiteStatistics;

class SqliteAnalysisStatsRepository implements AnalysisStatsRepositoryInterface
{
    /**
     * 回帰の x（julianday）から引く基準オフセット。生の julianday は約 2,460,000 と巨大で、
     * nΣx²−(Σx)² が「巨大な値同士の差」になり float64 で桁落ちする（slope/intercept が壊れ
     * CAGR が NaN/null 化）。データ開始(2023-10-16, julianday≈2,460,233)より前の固定値を引いて
     * x を数百〜千程度に収め数値安定化する（差分なので slope・履歴日数は不変）。
     */
    private const JULIAN_OFFSET = 2460000;

    public function getMemberAsOf(int $lo, int $hi, string $date): array
    {
        // 「id レンジ + date <= 基準日」での MAX(date) を 1 ルーム 1 行に畳む。畳み込み自体は
        // (open_chat_id, date) インデックスだけで完結し、member 行参照は確定した 1 日分のみ。
        $query =
            "SELECT s.open_chat_id AS oid, s.member AS m
            FROM statistics s
            JOIN (
                SELECT open_chat_id, MAX(date) AS d
                FROM statistics
                WHERE open_chat_id >= :lo AND open_chat_id < :hi AND date <= :date
                GROUP BY open_chat_id
            ) m ON s.open_chat_id = m.open_chat_id AND s.date = m.d";

        SQLiteStatistics::connect(SQLiteStatistics::WEB_READER);
        $rows = SQLiteStatistics::fetchAll($query, ['lo' => $lo, 'hi' => $hi, 'date' => $date]);

        $result = [];
        foreach ($rows as $r) {
            $result[(int)$r['oid']] = (int)$r['m'];
        }

        return $result;
    }

    public function getSteadyAggregates(int $lo, int $hi, string $fromDate, string $toDate): array
    {
        // member は (open_chat_id, date) インデックスに含まれず行参照が発生し、全日次だと
        // 1チャンク約28秒と重い。長期トレンドの回帰には日次の精度は不要なので、各月の
        // 01/11/21 日だけに間引く（substr 比較は date 列＝インデックス内で完結し、member の
        // 行参照を約1/10に削減＝約7〜10倍速）。MIN/MAX も間引き点での近似値になる。
        $off = self::JULIAN_OFFSET;
        $query =
            "SELECT
                open_chat_id,
                COUNT(*) AS n,
                MIN(julianday(date) - {$off}) AS jmin,
                MAX(julianday(date) - {$off}) AS jmax,
                MAX(member) AS peak,
                SUM(julianday(date) - {$off}) AS sx,
                SUM(member) AS sy,
                SUM((julianday(date) - {$off}) * member) AS sxy,
                SUM((julianday(date) - {$off}) * (julianday(date) - {$off})) AS sxx,
                SUM(CAST(member AS REAL) * member) AS syy,
                (
                    SELECT s2.member FROM statistics s2
                    WHERE s2.open_chat_id = statistics.open_chat_id AND s2.date >= :first_from
                    ORDER BY s2.date ASC LIMIT 1
                ) AS first_m
            FROM statistics
            WHERE open_chat_id >= :lo AND open_chat_id < :hi
                AND date >= :from AND date <= :to
                AND substr(date, 9, 2) IN ('01', '11', '21')
            GROUP BY open_chat_id";

        SQLiteStatistics::connect(SQLiteStatistics::WEB_READER);
        $rows = SQLiteStatistics::fetchAll($query, [
            'lo' => $lo,
            'hi' => $hi,
            'from' => $fromDate,
            'to' => $toDate,
            'first_from' => $fromDate,
        ]);

        $result = [];
        foreach ($rows as $r) {
            $result[(int)$r['open_chat_id']] = [
                'n' => (int)$r['n'],
                'jmin' => (float)$r['jmin'],
                'jmax' => (float)$r['jmax'],
                'peak' => (int)$r['peak'],
                'sx' => (float)$r['sx'],
                'sy' => (float)$r['sy'],
                'sxy' => (float)$r['sxy'],
                'sxx' => (float)$r['sxx'],
                'syy' => (float)$r['syy'],
                'first' => (int)$r['first_m'],
            ];
        }

        return $result;
    }
}

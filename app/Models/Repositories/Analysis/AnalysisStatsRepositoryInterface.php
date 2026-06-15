<?php

declare(strict_types=1);

namespace App\Models\Repositories\Analysis;

/**
 * 詳細成長分析（/analysis）の重い時系列集計を SQLite statistics から読む。
 *
 * 全ルーム横断・期間指定の生クエリは (open_chat_id, date) 複合インデックスのみの
 * 1億行テーブルに対しフルスキャンになるため、open_chat_id レンジごとの「チャンク集約」で
 * 分割実行する（1ルーム1行に畳んで返す＝低メモリ）。進捗表示・キャンセルのためにも分割が必要。
 */
interface AnalysisStatsRepositoryInterface
{
    /**
     * 指定日以前で最新のメンバー数（as-of）を open_chat_id ∈ [lo, hi) のルームについて返す。
     * その日までに実データが無い部屋は含まない（＝「その日に存在していた」判定にも使える）。
     *
     * @return array<int, int> open_chat_id => member
     */
    public function getMemberAsOf(int $lo, int $hi, string $date): array;

    /**
     * 全履歴の線形回帰に必要な総和（Σx,Σy,Σxy,Σx²,Σy², n, julianday の min/max, peak）を
     * open_chat_id ∈ [lo, hi) のルームごとに集約して返す。x は julianday(date)。
     *
     * first は実測の初回メンバー数（CAGR 用、サンプリングの影響を受けない最古の値）。
     *
     * @return array<int, array{n:int, jmin:float, jmax:float, peak:int, sx:float, sy:float, sxy:float, sxx:float, syy:float, first:int}>
     */
    public function getSteadyAggregates(int $lo, int $hi): array;
}

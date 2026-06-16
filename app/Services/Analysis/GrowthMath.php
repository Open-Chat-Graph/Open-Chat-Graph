<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * 詳細成長分析（/analysis）の指標計算の純粋関数群。
 *
 * - 期間増加: 期間始点(base)と終点(current)のメンバー差分・増加率
 * - じわじわ成長スコア: 全履歴の線形回帰（傾き×R²）を規模で正規化し、履歴長で重みづけ、
 *   ピークからの下落で減点した合成スコア。短期ランキングに埋もれる「数年かけて安定して
 *   伸びている部屋」を浮かせる。
 *
 * 回帰は SQLite 側の集約クエリで求めた総和（Σx, Σy, Σxy, Σx², Σy², n）から復元する
 * （全日次行を PHP に引かない＝低メモリ・高速）。x は julianday(date)。
 */
final class GrowthMath
{
    /** 対象とする最小の現在メンバー数（新規・極小部屋がノイズで上位を占めるのを防ぐ） */
    public const MIN_MEMBER = 50;

    /**
     * じわじわ成長スコアに必要な最小サンプル点数。
     * SQLite 側で月3日(01/11/21)に間引くため、3ヶ月窓(=約9点)でも足切りされない低めの値。
     */
    public const MIN_POINTS = 6;

    /**
     * 選択した期間窓のうち、部屋が在籍していなければならない割合。
     * これ未満（窓の途中で登録された部屋）は除外し、同じ期間で公平に比較する
     * （特に「全期間」での登録タイミングによる有利不利をケアする）。
     */
    public const WINDOW_COVERAGE = 0.6;

    /**
     * 増加率（%）。base<=0 のときは未定義(null)。
     */
    public static function safePct(int $base, int $current): ?float
    {
        if ($base <= 0) {
            return null;
        }

        return ($current - $base) / $base * 100.0;
    }

    /**
     * 期間増加の表示用データ。
     *
     * @return array{diff:int, pct:?float, symbol:string}
     *   symbol は 'positive' / 'negative' / ''（増減記号、フロントの色分け用）
     */
    public static function periodIncrease(int $base, int $current): array
    {
        $diff = $current - $base;
        $symbol = $diff > 0 ? 'positive' : ($diff < 0 ? 'negative' : '');

        return [
            'diff' => $diff,
            'pct' => self::safePct($base, $current),
            'symbol' => $symbol,
        ];
    }

    /**
     * 集約された総和から最小二乗回帰の傾き・切片・R² を復元する。
     *
     * @param array{n:int, sx:float, sy:float, sxy:float, sxx:float, syy:float} $s
     * @return array{slope:float, intercept:float, r2:float}|null
     *   分散が無い（全点同 x または n<2）場合は null
     */
    public static function regression(array $s): ?array
    {
        $n = $s['n'];
        if ($n < 2) {
            return null;
        }

        $denomX = $n * $s['sxx'] - $s['sx'] * $s['sx'];
        if ($denomX <= 0) {
            return null; // x に分散が無い
        }

        $slope = ($n * $s['sxy'] - $s['sx'] * $s['sy']) / $denomX;
        $intercept = ($s['sy'] - $slope * $s['sx']) / $n;

        $denomY = $n * $s['syy'] - $s['sy'] * $s['sy'];
        if ($denomY <= 0) {
            // y に分散が無い（完全に横ばい）→ 当てはまりは完全だが成長していない
            $r2 = 0.0;
        } else {
            $cov = $n * $s['sxy'] - $s['sx'] * $s['sy'];
            $r2 = ($cov * $cov) / ($denomX * $denomY);
            if ($r2 < 0.0) {
                $r2 = 0.0;
            } elseif ($r2 > 1.0) {
                $r2 = 1.0;
            }
        }

        return ['slope' => $slope, 'intercept' => $intercept, 'r2' => $r2];
    }

    /**
     * 選択した期間窓 [from,to] における「じわじわ成長スコア」と表示用メトリクスを計算する。
     *
     * 公平性のため:
     *  - 全部屋を同じ窓で評価し、窓の WINDOW_COVERAGE 割以上を在籍した部屋だけを対象にする
     *    （窓の途中で登録された部屋＝登録タイミングの有利不利を排除。特に「全期間」のケア）。
     *  - スコアは「年率換算の増加量(slope×365)」を使うので、窓の長さや部屋の年齢に依らず比較できる
     *    （古い＝有利、にならない）。安定性 R² を強く効かせ、ピーク下落で減点する。
     *
     * @param array{n:int, jmin:float, jmax:float, sx:float, sy:float, sxy:float, sxx:float, syy:float, peak:int, first:int} $agg
     * @param int $currentMember 現在のメンバー数（open_chat 由来）
     * @param int $windowDays 選択した期間窓の日数
     * @return array{score:float, slope:float, r2:float, cagr:?float, base:int, historyDays:int, points:int}|null
     *   足切り（在籍期間・メンバー・サンプル・分散）に掛かった場合は null
     */
    public static function steady(array $agg, int $currentMember, int $windowDays): ?array
    {
        $n = $agg['n'];
        $spanDays = (int) round($agg['jmax'] - $agg['jmin']); // 窓内で実際にデータがある期間
        $minSpan = (int) round($windowDays * self::WINDOW_COVERAGE);

        if (
            $n < self::MIN_POINTS
            || $currentMember < self::MIN_MEMBER
            || $spanDays < $minSpan
        ) {
            return null;
        }

        $reg = self::regression([
            'n' => $n,
            'sx' => $agg['sx'],
            'sy' => $agg['sy'],
            'sxy' => $agg['sxy'],
            'sxx' => $agg['sxx'],
            'syy' => $agg['syy'],
        ]);
        if ($reg === null) {
            return null;
        }

        $avg = $agg['sy'] / $n;
        if ($avg <= 0) {
            return null;
        }

        $slope = $reg['slope'];           // 1日あたりのメンバー増加
        $r2 = $reg['r2'];
        $years = $spanDays / 365.25;

        // CAGR は実測の初回メンバー→現在メンバーで算出（年平均成長率%）。
        $first = (int)($agg['first'] ?? 0);
        $cagr = ($first > 0 && $currentMember > 0 && $years > 0)
            ? (pow($currentMember / $first, 1 / $years) - 1) * 100.0
            : null;

        // ピークからの下落で減点（吹き上げて崩落した部屋を除外気味に）
        $peak = max(1, $agg['peak']);
        $drawdown = ($peak - $currentMember) / $peak;
        $drawdownFactor = 1.0 - max(0.0, $drawdown - 0.10) / 0.90;
        if ($drawdownFactor < 0.0) {
            $drawdownFactor = 0.0;
        }

        // スコア = 安定性(R²²) × 年率換算の増加量(対数) × 下落減点。
        // slope×365.25 は「1年あたりの増加」＝窓の長さ・部屋の年齢に依らない指標なので、
        // 3ヶ月窓でも全期間でも、また新しい部屋でも古い部屋でも公平に比較できる。
        $annualGrowth = $slope * 365.25;
        $growthTerm = $annualGrowth > 0 ? log(1 + $annualGrowth) : 0.0;
        $score = ($r2 ** 2) * $growthTerm * $drawdownFactor;

        return [
            'score' => $score,
            'slope' => $slope,
            'r2' => $r2,
            'cagr' => $cagr,
            'base' => $first,        // 窓開始時点のメンバー数（表示用 +N人(+X%) の基準）
            'historyDays' => $spanDays,
            'points' => $n,
        ];
    }
}

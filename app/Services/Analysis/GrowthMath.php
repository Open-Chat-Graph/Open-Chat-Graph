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
    /** じわじわ成長スコアの対象とする最小履歴日数 */
    public const MIN_HISTORY_DAYS = 365;

    /** 対象とする最小の現在メンバー数（新規・極小部屋がノイズで上位を占めるのを防ぐ） */
    public const MIN_MEMBER = 50;

    /**
     * じわじわ成長スコアに必要な最小サンプル点数。
     * 重い全 member 走査を避けるため SQLite 側で月3日(01/11/21)に間引くので、
     * 1年(=約36点)で足切りされないよう低めに設定する。
     */
    public const MIN_POINTS = 24;

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
     * じわじわ成長スコアと表示用メトリクスを計算する。
     *
     * 規模に依存しないよう傾きを平均メンバー数で正規化（slopeNorm＝1日あたりの相対成長）、
     * 当てはまりの良さ R² と履歴の長さ sqrt(years) で重みづけ、ピークからの下落で減点する。
     *
     * @param array{n:int, jmin:float, jmax:float, sx:float, sy:float, sxy:float, sxx:float, syy:float, peak:int, first:int} $agg
     * @param int $currentMember 現在のメンバー数（open_chat 由来）
     * @return array{score:float, slope:float, slopeNorm:float, r2:float, cagr:?float, historyDays:int, points:int}|null
     *   足切り（履歴・メンバー・サンプル・分散）に掛かった場合は null
     */
    public static function steady(array $agg, int $currentMember): ?array
    {
        $n = $agg['n'];
        $historyDays = (int) round($agg['jmax'] - $agg['jmin']);

        if (
            $n < self::MIN_POINTS
            || $historyDays < self::MIN_HISTORY_DAYS
            || $currentMember < self::MIN_MEMBER
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
        $slopeNorm = $slope / $avg;       // 平均規模に対する1日あたりの相対成長
        $r2 = $reg['r2'];
        $years = $historyDays / 365.25;

        // CAGR は実測の初回メンバー→現在メンバーで算出（年平均成長率%）。
        // 当てはめ直線の端点だと加速成長の部屋で始点が負になり未定義化するため、実測値を使う。
        $first = (int)($agg['first'] ?? 0);
        $cagr = ($first > 0 && $currentMember > 0 && $years > 0)
            ? (pow($currentMember / $first, 1 / $years) - 1) * 100.0
            : null;

        // ピークからの下落で減点（吹き上げて崩落した部屋を除外気味に）
        $peak = max(1, $agg['peak']);
        $drawdown = ($peak - $currentMember) / $peak;          // 0=ピーク維持, 1=ほぼ消失
        $drawdownFactor = 1.0 - max(0.0, $drawdown - 0.10) / 0.90; // 10%以内は無罰、90%下落で0
        if ($drawdownFactor < 0.0) {
            $drawdownFactor = 0.0;
        }

        // 「数年かけてじわじわ」= 安定性(R²)と継続年数を重視し、規模の暴発は対数で抑える。
        //  - R²²: 直線的な一貫成長（=じわじわ）を強く評価。ガタつく急成長を相対的に下げる
        //  - years: 長期ほど高評価（線形）
        //  - log(1+|増加|)×符号: 実増加量。小規模ゼロ近傍からの急騰が相対比で上位を独占するのを防ぐ
        //    （増加が負＝縮小はスコアも負になり、昇順ソートで縮小室を出せる）
        //  - drawdownFactor: 吹き上げ→崩落を減点
        $growth = $currentMember - $first;
        $growthTerm = ($growth >= 0 ? 1.0 : -1.0) * log(1 + abs($growth));
        $score = ($r2 ** 2) * $years * $growthTerm * $drawdownFactor;

        return [
            'score' => $score,
            'slope' => $slope,
            'slopeNorm' => $slopeNorm,
            'r2' => $r2,
            'cagr' => $cagr,
            'historyDays' => $historyDays,
            'points' => $n,
        ];
    }
}

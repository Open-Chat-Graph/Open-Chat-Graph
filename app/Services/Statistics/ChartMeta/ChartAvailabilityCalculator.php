<?php

declare(strict_types=1);

namespace App\Services\Statistics\ChartMeta;

/**
 * グラフ初回ロードのタブ/ボタン出し分け「可用性メタ」のしきい値判定（純粋ロジック）。
 *
 * 可用性メタの組み立ては OcPageCacheDataBuilder 1本に集約されており（ページ埋め込みの事前計算も
 * meta=1 のライブ計算も同じ Builder を通す）、本クラスはそのしきい値判定だけを担う。
 * COUNT 結果（DB依存）の取得は呼び出し側に残し、本クラスはDIも状態も持たない。
 */
class ChartAvailabilityCalculator
{
    /**
     * 期間タブ毎のローソク足(OHLC)データ有無を判定する。
     *
     * 各期間タブはグラフ末尾からの日数ウィンドウ（1週間=最大8件, 1ヶ月=最大31件）を表示するため、
     * ウィンドウ内のOHLC件数で判定する。
     * - 1週間: ウィンドウ内の全日分が揃っている場合のみ有効
     * - 1ヶ月: ウィンドウ内の半分以上の日にあれば有効
     * - 全期間: 1件でもあれば有効（all_count===0 のときは全て無効）
     *
     * @param array{ all_count: int, week_count: int, month_count: int } $ohlcCounts
     * @return array{ week: bool, month: bool, all: bool }
     */
    public static function dailyOhlc(array $ohlcCounts, int $weekWindow, int $monthWindow): array
    {
        if ($ohlcCounts['all_count'] === 0) {
            return ['week' => false, 'month' => false, 'all' => false];
        }

        return [
            'week' => $ohlcCounts['week_count'] >= $weekWindow,
            'month' => $ohlcCounts['month_count'] * 2 >= $monthWindow,
            'all' => true,
        ];
    }

    /**
     * 期間タブ×ランキング種別(ranking/rising)×カテゴリ(in/all)毎の順位データ有無を判定する。
     *
     * @param array<'ranking_in'|'ranking_all'|'rising_in'|'rising_all', array{ week: int, month: int, all: int }> $counts
     * @return array<'week'|'month'|'all', array{ ranking_in: bool, ranking_all: bool, rising_in: bool, rising_all: bool }>
     */
    public static function dailyPosition(array $counts): array
    {
        $result = [];
        foreach (['week', 'month', 'all'] as $period) {
            $result[$period] = [
                'ranking_in' => $counts['ranking_in'][$period] > 0,
                'ranking_all' => $counts['ranking_all'][$period] > 0,
                'rising_in' => $counts['rising_in'][$period] > 0,
                'rising_all' => $counts['rising_all'][$period] > 0,
            ];
        }

        return $result;
    }
}

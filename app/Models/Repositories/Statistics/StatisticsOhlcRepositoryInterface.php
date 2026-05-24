<?php

declare(strict_types=1);

namespace App\Models\Repositories\Statistics;

interface StatisticsOhlcRepositoryInterface
{
    /**
     * @param array{ open_chat_id: int, open_member: int, high_member: int, low_member: int, close_member: int, date: string }[] $data
     */
    public function insertOhlc(array $data): int;

    /**
     * メンバー数OHLCを日付昇順で取得する。
     *
     * - OHLC統計の記録開始以降のデータのみ含まれる
     *   （記録開始前の日次メンバー数データが存在してもOHLCレコードは無い）
     *
     * @return array{ date: string, open_member: int, high_member: int, low_member: int, close_member: int }[]
     */
    public function getOhlcDateAsc(int $open_chat_id): array;

    /**
     * narrative 生成用のメンバー数メトリクスを 1 クエリで集約取得する。
     *
     * - 7日/30日/90日前以前で最新の close_member（sparse データ対応のため固定日付ではなく <=）
     * - 直近 200 件の OHLC を母集団として、ピーク (high_member) 日、単日最大伸び (close-open) を抽出
     * - データが無い場合は対応フィールドが NULL になる
     *
     * @return array{
     *     curr: ?int,
     *     curr_date: ?string,
     *     m7: ?int,
     *     m30: ?int,
     *     m90: ?int,
     *     sample_n: int,
     *     peak_high: ?int,
     *     peak_date: ?string,
     *     max_single_day_growth: ?int,
     *     max_growth_date: ?string,
     *     first_date: ?string
     * }
     */
    public function getMemberMetricsForNarrative(int $open_chat_id): array;
}

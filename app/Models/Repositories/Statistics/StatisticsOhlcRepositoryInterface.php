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
     * - $from と $to を両方与えると `date BETWEEN :from AND :to`（両端含む）で範囲を絞る。
     *   片方でも null なら従来どおり全期間を返す。
     *
     * @param ?string $from `Y-m-d` 範囲開始日（$to と併用時のみ有効）
     * @param ?string $to   `Y-m-d` 範囲終了日（$from と併用時のみ有効）
     * @return array{ date: string, open_member: int, high_member: int, low_member: int, close_member: int }[]
     */
    public function getOhlcDateAsc(int $open_chat_id, ?string $from = null, ?string $to = null): array;

    /**
     * メンバー数OHLCの件数を全期間・指定日以降（週/月ウィンドウ）でまとめて取得する。
     *
     * @param string $weekStartDate `Y-m-d` 週ウィンドウの開始日
     * @param string $monthStartDate `Y-m-d` 月ウィンドウの開始日
     * @return array{ all_count: int, week_count: int, month_count: int }
     */
    public function getOhlcCounts(int $open_chat_id, string $weekStartDate, string $monthStartDate): array;
}

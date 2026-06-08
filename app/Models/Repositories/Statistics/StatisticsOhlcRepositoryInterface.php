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
     * メンバー数OHLCの件数を全期間・指定日以降（週/月ウィンドウ）でまとめて取得する。
     *
     * @param string $weekStartDate `Y-m-d` 週ウィンドウの開始日
     * @param string $monthStartDate `Y-m-d` 月ウィンドウの開始日
     * @return array{ all_count: int, week_count: int, month_count: int }
     */
    public function getOhlcCounts(int $open_chat_id, string $weekStartDate, string $monthStartDate): array;
}

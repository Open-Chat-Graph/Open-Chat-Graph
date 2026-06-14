<?php

declare(strict_types=1);

namespace App\Models\Repositories\Statistics;

interface StatisticsPageRepositoryInterface
{
    /**
     * 日毎のメンバー数の統計を取得する
     *
     * $from と $to を両方与えると `date BETWEEN :from AND :to`（両端含む）で範囲を絞る。
     * 片方でも null なら従来どおり全期間を返す。
     *
     * @param ?string $from `Y-m-d` 範囲開始日（$to と併用時のみ有効）
     * @param ?string $to   `Y-m-d` 範囲終了日（$from と併用時のみ有効）
     * @return array{ date: string, member: int }[] date: Y-m-d
     */
    public function getDailyMemberStatsDateAsc(int $open_chat_id, ?string $from = null, ?string $to = null): array;

    /**
     * メンバー数統計の最古・最新の日付を取得する。
     *
     * 可用性キャッシュの日次再計算で、各部屋の表示ウィンドウ（週/月/全）の開始日を
     * 算出するために使う軽量メソッド（(open_chat_id, date) 索引で MIN/MAX は高速）。
     * レコードが無ければ null。
     *
     * @return array{ min: string, max: string }|null Y-m-d
     */
    public function getMemberDateRange(int $open_chat_id): ?array;
}

<?php

declare(strict_types=1);

namespace App\Models\Repositories\Statistics;

interface StatisticsPageRepositoryInterface
{
    /**
     * 日毎のメンバー数の統計を取得する
     *
     * @return array{ date: string, member: int }[] date: Y-m-d
     */
    public function getDailyMemberStatsDateAsc(int $open_chat_id): array;

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

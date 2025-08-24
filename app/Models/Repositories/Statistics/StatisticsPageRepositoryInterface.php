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
}

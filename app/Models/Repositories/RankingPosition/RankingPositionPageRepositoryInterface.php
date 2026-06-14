<?php

declare(strict_types=1);

namespace App\Models\Repositories\RankingPosition;

use App\Models\Repositories\RankingPosition\Dto\RankingPositionPageRepoDto;
use App\Services\OpenChat\Enum\RankingType;

interface RankingPositionPageRepositoryInterface
{
    /**
     * 日次の順位データを取得する。
     *
     * $from と $to を両方与えると `date(time) BETWEEN :from AND :to`（両端含む）で範囲を絞る。
     * 片方でも null なら従来どおり全期間を返す。
     *
     * @param ?string $from `Y-m-d` 範囲開始日（$to と併用時のみ有効）
     * @param ?string $to   `Y-m-d` 範囲終了日（$from と併用時のみ有効）
     */
    public function getDailyPosition(
        RankingType $type,
        int $open_chat_id,
        int $category,
        ?string $from = null,
        ?string $to = null
    ): RankingPositionPageRepoDto;

    /** @return array{ time:string,position:int,total_count_ranking:int } */
    public function getFinalRankingPosition(int $open_chat_id, int $category): array|false;
}

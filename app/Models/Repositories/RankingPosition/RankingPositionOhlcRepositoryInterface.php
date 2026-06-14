<?php

declare(strict_types=1);

namespace App\Models\Repositories\RankingPosition;

use App\Services\OpenChat\Enum\RankingType;

interface RankingPositionOhlcRepositoryInterface
{
    /**
     * @param array{ open_chat_id: int, category: int, type: string, open_position: int, high_position: int, low_position: int, close_position: int, date: string }[] $data
     */
    public function insertOhlc(array $data): int;

    /**
     * ランキング順位OHLCを日付昇順で取得する。
     *
     * - ランキングに一度も掲載されなかった日（完全に圏外）のレコードは含まれない
     * - low_position は、その日の全時間帯でランクインしていた場合は最低順位、
     *   一部の時間帯で圏外だった場合は NULL
     * - $from と $to を両方与えると `date BETWEEN :from AND :to`（両端含む）で範囲を絞る。
     *   片方でも null なら従来どおり全期間を返す。
     *
     * @param ?string $from `Y-m-d` 範囲開始日（$to と併用時のみ有効）
     * @param ?string $to   `Y-m-d` 範囲終了日（$from と併用時のみ有効）
     * @return array{ date: string, open_position: int, high_position: int, low_position: int|null, close_position: int }[]
     */
    public function getOhlcDateAsc(int $open_chat_id, int $category, RankingType $type, ?string $from = null, ?string $to = null): array;

    /**
     * narrative 生成用、直近 N 日のカテゴリ内順位の起点と最新値を取得する。
     *
     * - 範囲内の close_position の最古値 (oldest_close) と最新値 (latest_close)、最高位 (best_high) を集約
     * - 範囲内にレコードが無い場合は全フィールドが NULL
     * - low_position の集計は安定性のため避ける（NULL 含む可能性あり）
     *
     * @return array{
     *     oldest_close: ?int,
     *     oldest_date: ?string,
     *     latest_close: ?int,
     *     latest_date: ?string,
     *     best_high: ?int,
     *     sample_n: int
     * }
     */
    public function getRecentPositionMovement(int $open_chat_id, int $category, RankingType $type, int $days): array;

    /**
     * 直近 N 日の close_position の平均と観測日数を取得する。
     *
     * - 範囲内に 1 件も無い場合は ['avg_position' => null, 'sample_n' => 0]
     * - narrative 生成で「全体 ranking で常時上位 = 大規模代表」
     *   「全体 rising で常時上位 = 非常に活発」の判定に使う想定
     *
     * @return array{avg_position: ?float, sample_n: int}
     */
    public function getAveragePosition(int $open_chat_id, int $category, RankingType $type, int $days): array;
}

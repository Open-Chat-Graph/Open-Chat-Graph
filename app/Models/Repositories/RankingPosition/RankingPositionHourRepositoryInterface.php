<?php

declare(strict_types=1);

namespace App\Models\Repositories\RankingPosition;

use App\Services\OpenChat\Enum\RankingType;

interface RankingPositionHourRepositoryInterface
{
    /**
     * @param RankingPositionHourInsertDto[] $insertDtoArray
     */
    public function insertFromDtoArray(RankingType $type, string $fileTime, array $insertDtoArray): int;

    public function insertHourMemberFromDtoArray(string $fileTime, array $insertDtoArray): int;

    /**
     * @return array{ open_chat_id: int, member: int, date: string }[]
     */
    public function getDailyMemberStats(\DateTime $todayLastTime): array;

    /**
     * @return array{ open_chat_id: int, open_member: int, high_member: int, low_member: int, close_member: int, date: string }[]
     */
    public function getDailyMemberOhlc(\DateTime $todayLastTime): array;

    /**
     * @return array{ open_chat_id: int, member: int }[]
     */
    public function getHourlyMemberColumn(\DateTime $lastTime): array;

    /**
     * @return array{ open_chat_id: int, category: int, position: int, time: stirng }[]
     */
    public function getDaliyRanking(\DateTime $date, bool $all = false): array;

    /**
     * @return array{ open_chat_id: int, category: int, position: int, time: stirng }[]
     */
    public function getDailyRising(\DateTime $date, bool $all = false): array;

    /**
     * @return array{ category: int, total_count_rising: int, total_count_ranking: int, time: string }
     */
    public function getTotalCount(\DateTime $date, bool $isDate = true): array;

    public function delete(\DateTime $dateTime): void;

    /**
     * @return array{total_count_all_category_rising:int, total_count_all_category_ranking:int}
     */
    public function insertTotalCount(string $fileTime): array;

    /**
     * 指定日の毎時ランキングデータからOHLCを集約する。
     *
     * - その日にランキングに一度でも掲載されたルームのみレコードを生成する
     *   （終日圏外のルームはレコードなし）
     * - low_position: 全時間帯でランクインしていた場合は最低順位、
     *   一部の時間帯で圏外だった場合は NULL
     *
     * @return array{ open_chat_id: int, category: int, open_position: int, high_position: int, low_position: int|null, close_position: int, date: string }[]
     */
    public function getDailyPositionOhlc(RankingType $type, \DateTime $date): array;

    /**
     * @return string|false Y-m-d H:i:s
     */
    public function getLastHour(int $offset = 0): string|false;

    /**
     * 最新24時間ウィンドウ内の毎時データ件数（メンバー数・ランキング種別×カテゴリ毎の掲載数）を取得する。
     *
     * @param int $inCategory カテゴリ内判定に使うカテゴリID（カテゴリ無しの部屋は -1 等の不一致値を渡す）
     * @param \DateTime $endTime ウィンドウの終端（最新クロール時刻）
     * @return array{ member: int, ranking_in: int, ranking_all: int, rising_in: int, rising_all: int }
     */
    public function getHourPositionCounts(
        int $open_chat_id,
        int $inCategory,
        int $intervalHour,
        \DateTime $endTime
    ): array;

    /**
     * 最新 intervalHour 時間ウィンドウ内に出現した全部屋分の毎時データを、種別ごと1本の
     * GROUP BY で一括取得する（部屋数ぶんのクエリを撃たないバックフィル用）。
     *
     * 返り値は open_chat_id をキーにした連想配列:
     * - member:  その部屋がウィンドウ内に member レコードを1件以上持つか
     * - ranking: その部屋が ranking に出現した category の配列（全体ランキングは 0 を含む）
     * - rising:  その部屋が rising に出現した category の配列（同上）
     *
     * カテゴリ内(in)判定は呼び出し側で in_array($category, ranking/rising, true) する
     * （getHourPositionCounts の category=:in_category と等価。未掲載室の in は常に false）。
     *
     * @param \DateTime $endTime ウィンドウの終端（最新クロール時刻）
     * @return array<int, array{member: bool, ranking: int[], rising: int[]}>
     */
    public function getHourPositionCountsAll(int $intervalHour, \DateTime $endTime): array;
}

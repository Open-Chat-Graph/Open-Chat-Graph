<?php

declare(strict_types=1);

namespace App\Models\Repositories\Statistics;

use App\Services\OpenChat\Dto\OpenChatDto;

interface StatisticsRepositoryInterface
{
    public function addNewOpenChatStatisticsFromDto(OpenChatDto $dto): void;

    /**
     * @param string $date Y-m-d
     */
    public function insertDailyStatistics(int $open_chat_id, int $member, string $date): void;

    public function deleteDailyStatistics(int $open_chat_id): void;

    /**
     * レコード数が8以下の新規部屋を取得
     * 高速クエリ（約5秒）のため、毎時実行して新規ルームのリアルタイム性を確保
     *
     * @return int[] open_chat_id
     */
    public function getNewRoomsWithLessThan8Records(): array;

    /**
     * 過去8日間でメンバー数が変動した部屋を取得
     *
     * @return int[] open_chat_id
     */
    public function getMemberChangeWithinLastWeek(string $date): array;

    /**
     * 最後のレコードが1週間以上前の部屋を取得（週次更新用）
     *
     * @return int[] open_chat_id
     */
    public function getWeeklyUpdateRooms(string $date): array;

    /**
     * @param array{ open_chat_id: int, member: int, date: string }[] $data
     */
    public function insertMember(array $data): int;

    /**
     * @param string $date Y-m-d
     * @return int[]
     */
    public function getOpenChatIdArrayByDate(string $date): array;

    /**
     * 指定した日付・IDのメンバー数を取得する
     * 
     * @param string $date Y-m-d
     * 
     * @return int
     */
    public function getMemberCount(int $open_chat_id, string $date): int|false;

    /**
     * narrative 用のメンバー数メトリクス (daily の member スナップショットから集約)。
     *
     * statistics_ohlc はランキング掲載日しか記録されず欠損が出るため、欠損のない
     * daily の statistics テーブルを使う。単日最大伸びは日次差分 (LAG) で算出。
     *
     * @return array{
     *     curr: ?int, curr_date: ?string,
     *     m7: ?int, m30: ?int, m90: ?int,
     *     sample_n: int,
     *     peak_high: ?int, peak_date: ?string,
     *     max_single_day_growth: ?int, max_growth_date: ?string,
     *     first_date: ?string
     * }
     */
    public function getMemberMetricsForNarrative(int $open_chat_id): array;
}

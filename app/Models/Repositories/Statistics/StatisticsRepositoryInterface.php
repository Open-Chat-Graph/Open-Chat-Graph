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
     * @return int[] open_chat_id
     */
    public function getMemberChangeWithinLastWeekCacheArray(string $date): array;

    /**
     * @return int[] open_chat_id
     */
    public function getHourMemberChangeWithinLastWeekArray(string $date): array;

    /**
     * レコード数が8以下の新規部屋を取得
     * 高速クエリ（約5秒）のため、毎時実行して新規ルームのリアルタイム性を確保
     *
     * @return int[] open_chat_id
     */
    public function getNewRoomsWithLessThan8Records(): array;

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
}

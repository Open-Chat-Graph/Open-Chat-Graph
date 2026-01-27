<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Config\AppConfig;
use App\Models\Repositories\DB;
use App\Services\OpenChat\Dto\OpenChatDto;
use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;

class OpenChatRepository implements OpenChatRepositoryInterface
{
    private static int $insertCount = 0;

    public function __construct(
        private StatisticsRepositoryInterface $statisticsRepository
    ) {
    }

    public static function getInsertCount(): int
    {
        return static::$insertCount;
    }

    public static function resetInsertCount(): void
    {
        static::$insertCount = 0;
    }

    public function addOpenChatFromDto(OpenChatDto $dto): int|false
    {
        $dto->registered_open_chat_id = DB::executeAndGetLastInsertId(
            "INSERT IGNORE INTO
                open_chat (
                    name,
                    img_url,
                    description,
                    member,
                    created_at,
                    updated_at,
                    emid,
                    category,
                    api_created_at,
                    emblem,
                    join_method_type,
                    url
                )
            VALUES
                (
                    :name,
                    :img_url,
                    :description,
                    :member,
                    FROM_UNIXTIME(:created_at),
                    FROM_UNIXTIME(:created_at),
                    :emid,
                    :category,
                    :api_created_at,
                    :emblem,
                    :join_method_type,
                    :url
                )",
            [
                'name' => $dto->name,
                'description' => $dto->desc,
                'img_url' => $dto->profileImageObsHash,
                'member' => $dto->memberCount,
                'emid' => $dto->emid,
                'created_at' => $dto->registered_created_at,
                'api_created_at' => $dto->createdAt,
                'category' => $dto->category,
                'emblem' => $dto->emblem,
                'join_method_type' => $dto->joinMethodType,
                'url' => $dto->invitationTicket,
            ]
        );

        if (!$dto->registered_open_chat_id) {
            return false;
        }

        $this->statisticsRepository->addNewOpenChatStatisticsFromDto($dto);

        static::$insertCount++;
        return $dto->registered_open_chat_id;
    }

    public function getOpenChatIdEmidArrayAll(): array
    {
        return DB::fetchAll("SELECT id, emid FROM open_chat");
    }

    public function getOpenChatIdAll(): array
    {
        $query =
            'SELECT
                id
            FROM
                open_chat';

        return DB::fetchAll($query, null, [\PDO::FETCH_COLUMN, 0]);
    }

    public function getOpenChatIdAllByCreatedAtDate(string $date): array
    {
        $date = new \DateTime($date);
        $date->modify('+1day');
        $dateStr = $date->format('Y-m-d');

        $query =
            "SELECT
                id
            FROM
                open_chat
            WHERE
                created_at < '{$dateStr}'";

        return DB::fetchAll($query, null, [\PDO::FETCH_COLUMN, 0]);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Models\Repositories\DB;
use App\Services\OpenChat\Dto\OpenChatRepositoryDto;
use App\Services\OpenChat\Dto\OpenChatUpdaterDto;

class UpdateOpenChatRepository implements UpdateOpenChatRepositoryInterface
{
    public function __construct(
        private DeleteOpenChatRepositoryInterface $DeleteOpenChatRepository
    ) {
    }

    public function getOpenChatDataById(int $id): OpenChatRepositoryDto|false
    {
        $query =
            'SELECT
                emid,
                name,
                description,
                img_url,
                local_img_url,
                member,
                api_created_at,
                category,
                emblem,
                url
            FROM
                open_chat AS oc
            WHERE
                oc.id = :id';

        $result = DB::fetch($query, ['id' => $id]);
        if (!$result) {
            return false;
        }

        return new OpenChatRepositoryDto($id, $result);
    }

    public function updateOpenChatRecord(OpenChatUpdaterDto $dto): void
    {
        if ($dto->delete_flag === true) {
            $this->DeleteOpenChatRepository->deleteOpenChat($dto->open_chat_id);
            return;
        }

        $columnsToSet = [
            'updated_at' => $dto->updated_at ?? null,
            'emid' => $dto->emid ?? null,
            'name' => $dto->name ?? null,
            'description' => $dto->desc ?? null,
            'img_url' => $dto->profileImageObsHash ?? null,
            'member' => $dto->memberCount ?? null,
            'api_created_at' => $dto->createdAt ?? null,
            'category' => $dto->category ?? null,
            'emblem' => $dto->emblem ?? null,
            'join_method_type' => $dto->joinMethodType ?? null,
            'update_items' => $dto->getUpdateItems(),
        ];

        $columnsToUpdate = array_filter($columnsToSet, fn ($value) => $value !== null);
        if (!$columnsToUpdate) {
            return;
        }

        $setStatement = implode(',', array_map(fn ($column) => "{$column} = :{$column}", array_keys($columnsToUpdate)));

        $columnsToUpdate['id'] = $dto->open_chat_id;

        DB::execute(
            "UPDATE 
                open_chat 
            SET 
                {$setStatement} 
            WHERE 
                id = :id",
            $columnsToUpdate
        );
    }

    public function updateLocalImgUrl(int $open_chat_id, string $local_img_url): void
    {
        DB::execute(
            "UPDATE 
                open_chat 
            SET 
                local_img_url = :local_img_url
            WHERE 
                id = :open_chat_id",
            compact('local_img_url', 'open_chat_id')
        );
    }

    public function updateUrl(int $open_chat_id, string $url): void
    {
        DB::execute(
            "UPDATE 
                open_chat 
            SET 
                url = :url
            WHERE 
                id = :open_chat_id",
            compact('url', 'open_chat_id')
        );
    }

    public function getOpenChatIdByEmid(string $emid): int|false
    {
        $query =
            'SELECT
                id
            FROM
                open_chat
            WHERE
                emid = :emid';

        return DB::fetchColumn($query, compact('emid'));
    }

    public function updateMemberColumn(array $oc): void
    {
        $member = $oc['member'];
        $id = $oc['open_chat_id'];

        DB::$pdo->exec("UPDATE open_chat SET member = {$member} WHERE id = {$id}");
    }

    public function getUpdatedOpenChatBetweenUpdatedAt(\DateTime $start, \DateTime $end): array
    {
        $query =
            "SELECT
                id,
                img_url,
                local_img_url
            FROM
                open_chat
            WHERE
                updated_at BETWEEN :start AND :end";

        return DB::fetchAll($query, [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ]);
    }

    public function getOpenChatImgAll(?string $date = null): array
    {
        $query =
            "SELECT
                id,
                img_url,
                local_img_url
            FROM
                open_chat
            " . ($date ? "WHERE
                updated_at >= '{$date}'" : "");

        return DB::fetchAll($query);
    }

    public function getEmptyUrlOpenChatId(): array
    {
        $query =
            "SELECT
                id,
                emid
            FROM
                open_chat
            WHERE
                url IS NULL";

        return DB::fetchAll($query);
    }
}

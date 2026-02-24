<?php

declare(strict_types=1);

namespace App\Models\RecommendRepositories;

use App\Models\Repositories\DB;

class ModifyRecommendRepository implements ModifyRecommendRepositoryInterface
{
    function getModifyTag(int $id): string|false
    {
        return DB::fetchColumn(
            "SELECT tag FROM modify_recommend WHERE id = :id",
            compact('id')
        );
    }

    function upsertModifyTag(int $id, string $tag): void
    {
        DB::execute(
            "INSERT INTO modify_recommend (id, tag) VALUES(:id, :tag)
                ON DUPLICATE KEY UPDATE tag = :tag2",
            ['id' => $id, 'tag' => $tag, 'tag2' => $tag]
        );
    }

    function upsertRecommendTag(int $id, string $tag): void
    {
        DB::execute(
            "INSERT INTO recommend (id, tag) VALUES(:id, :tag)
                ON DUPLICATE KEY UPDATE tag = :tag2",
            ['id' => $id, 'tag' => $tag, 'tag2' => $tag]
        );
    }

    function deleteRecommendTag(int $id): void
    {
        DB::execute(
            "DELETE FROM recommend WHERE id = :id",
            compact('id')
        );
    }

    function deleteModifyTag(int $id): void
    {
        DB::execute(
            "DELETE FROM modify_recommend WHERE id = :id",
            compact('id')
        );
    }
}

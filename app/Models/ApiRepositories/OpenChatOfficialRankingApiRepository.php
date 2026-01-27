<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories;

use App\Models\RankingPositionDB\RankingPositionDB;
use App\Services\OpenChat\Enum\RankingType;
use App\Models\Repositories\DB;

class OpenChatOfficialRankingApiRepository
{
    private function getOpenChat(array $idArray): array
    {
        $ids = implode(",", $idArray);
        $query =
            "SELECT
                oc.id,
                oc.name,
                oc.description,
                oc.member,
                oc.img_url,
                oc.emblem,
                oc.join_method_type,
                oc.category,
                sr.diff_member,
                sr.percent_increase
            FROM
                open_chat AS oc
                LEFT JOIN statistics_ranking_hour AS sr ON oc.id = sr.open_chat_id
            WHERE
                oc.id IN ({$ids})
            ORDER BY
                FIELD(oc.id,{$ids});";

        return array_map(
            fn ($oc) => new OpenChatListDto($oc),
            DB::fetchAll($query)
        );
    }

    function findOfficialRanking(
        OpenChatApiArgs $args,
        RankingType $type,
        string $time
    ): array {
        $offset = $args->page * $args->limit;
        $limit = $args->limit;
        $category = $args->category;
        $table = $type->value;

        $idArray = RankingPositionDB::fetchAll(
            "SELECT
                open_chat_id
            FROM
                {$table}
            WHERE
                category = :category
                AND time = :time
            ORDER BY
                position ASC
            LIMIT
                :offset, :limit",
            compact('offset', 'limit', 'category', 'time'),
            [\PDO::FETCH_COLUMN]
        );

        if (!$idArray)
            return [];

        $result = $this->getOpenChat($idArray);
        if ($args->page !== 0)
            return $result;

        $result[0]->totalCount = RankingPositionDB::fetchColumn(
            "SELECT 
                count(*)
            FROM
                {$table}
            WHERE
                category = :category
                AND time = :time",
            compact('category', 'time'),
        );

        return $result;
    }
}

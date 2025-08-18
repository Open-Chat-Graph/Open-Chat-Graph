<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Models\Repositories\DB;

class OpenChatPageRepository implements OpenChatPageRepositoryInterface
{
    public function getOpenChatById(int $id): array|false
    {
        $query =
            "SELECT
                oc.id,
                oc.name,
                oc.local_img_url AS img_url,
                oc.img_url AS api_img_url,
                oc.description,
                oc.member,
                oc.api_created_at,
                oc.emblem,
                oc.category,
                oc.emid,
                oc.updated_at,
                oc.url,
                oc.created_at,
                oc.updated_at,
                oc.join_method_type,
                rh.diff_member AS rh_diff_member,
                rh.percent_increase AS rh_percent_increase,
                rh24.diff_member AS rh24_diff_member,
                rh24.percent_increase AS rh24_percent_increase
            FROM
                open_chat AS oc
                LEFT JOIN statistics_ranking_hour AS rh ON oc.id = rh.open_chat_id
                LEFT JOIN statistics_ranking_hour24 AS rh24 ON oc.id = rh24.open_chat_id
            WHERE
                oc.id = :id";

        $result = DB::fetch($query, ['id' => $id]);
        
        return $result ? $result + ['tag1' => null, 'tag2' => null, 'tag3' => null] : false;
    }

    public function getOpenChatByIdWithTag(int $id): array|false
    {
        $query =
            "SELECT
                oc.id,
                oc.name,
                oc.local_img_url AS img_url,
                oc.img_url AS api_img_url,
                oc.description,
                oc.member,
                oc.api_created_at,
                oc.emblem,
                oc.category,
                oc.emid,
                oc.updated_at,
                oc.url,
                oc.created_at,
                oc.updated_at,
                oc.join_method_type,
                rh.diff_member AS rh_diff_member,
                rh.percent_increase AS rh_percent_increase,
                rh24.diff_member AS rh24_diff_member,
                rh24.percent_increase AS rh24_percent_increase,
                tg.tag AS tag1,
                tg2.tag AS tag2,
                tg3.tag AS tag3
            FROM
                open_chat AS oc
                LEFT JOIN statistics_ranking_hour AS rh ON oc.id = rh.open_chat_id
                LEFT JOIN statistics_ranking_hour24 AS rh24 ON oc.id = rh24.open_chat_id
                LEFT JOIN recommend AS tg ON oc.id = tg.id
                LEFT JOIN oc_tag AS tg2 ON oc.id = tg2.id
                LEFT JOIN oc_tag2 AS tg3 ON oc.id = tg3.id
            WHERE
                oc.id = :id";

        return DB::fetch($query, ['id' => $id]);
    }

    public function isExistsOpenChat(int $id): bool
    {
        $query =
            "SELECT
                oc.id
            FROM
                open_chat AS oc
            WHERE
                oc.id = :id";

        return !!DB::fetchColumn($query, ['id' => $id]);
    }
}

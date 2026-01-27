<?php

declare(strict_types=1);

namespace App\Models\UserLogRepositories;

use App\Models\Repositories\DB;

class UserLogRepository
{
    /**
     * @param int[] $ocList
     */
    function insertUserListLog(
        array $ocList,
        string $user_id,
        int $expires,
        string $ip,
        string $ua
    ): void {
        $query =
            "INSERT INTO oc_list_user
            (user_id, oc_list, list_count, expires, ip, ua)
        VALUES
            (:user_id, :oc_list, :list_count, :expires, :ip, :ua)
        ON DUPLICATE KEY UPDATE
            oc_list = VALUES(oc_list),
            list_count = VALUES(list_count),
            expires = VALUES(expires),
            ip = VALUES(ip),
            ua = VALUES(ua);";

        $oc_list = json_encode($ocList);
        $list_count = count($ocList);

        UserLogDB::execute($query, compact(
            'user_id',
            'oc_list',
            'list_count',
            'expires',
            'ip',
            'ua'
        ));
    }

    function checkExistsUserListLog(string $user_id, int $expires): bool
    {
        $query =
            "SELECT
                user_id
            FROM
                oc_list_user
            WHERE
                user_id = :user_id
                AND expires = :expires";

        return !!UserLogDB::fetch($query, compact('user_id', 'expires'));
    }

    function insertUserListShowLog(string $user_id)
    {
        $query =
            "INSERT INTO oc_list_user_list_show_log
                (user_id)
            VALUES
                (:user_id)";

        UserLogDB::execute($query, compact('user_id'));
    }

    private function getOcQuery(string $ids)
    {
        return
            "SELECT
                id,
                name,
                img_url,
                description,
                member,
                category,
                emblem,
                join_method_type
            FROM
                open_chat
            WHERE
                id IN ({$ids})";
    }

    function getUserListLogAll(int $limit, int $offset): array
    {
        $query =
            "SELECT
                t1.*,
                t2.count,
                t2.time
            FROM
                oc_list_user AS t1
                JOIN (
                    SELECT
                        count(*) AS count,
                        user_id,
                        MAX(time) AS time
                    FROM
                        oc_list_user_list_show_log
                    GROUP BY
                        user_id
                ) AS t2 ON t1.user_id = t2.user_id
            ORDER BY
                t2.time DESC
            LIMIT
                :offset, :limit";

        $params = compact('limit', 'offset');
        $list = UserLogDB::fetchAll($query, $params);
        if (!$list) return [];

        foreach ($list as $key => $el) {
            $ids = implode(',', json_decode($el['oc_list']));
            $query = $this->getOcQuery($ids);
            $ocs = DB::fetchAll($query);

            // メンバーが多い順で並び替え
            usort($ocs, fn ($a, $b) => ($b['member']) - ($a['member']));
            $list[$key]['oc'] = $ocs;
        }

        return $list;
    }
}

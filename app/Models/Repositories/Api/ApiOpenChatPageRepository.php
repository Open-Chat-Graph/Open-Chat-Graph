<?php

declare(strict_types=1);

namespace App\Models\Repositories\Api;

use App\Models\Repositories\OpenChatPageRepositoryInterface;

/**
 * Repository for OpenChat page data from ocgraph_sqlapi database
 * Implements OpenChatPageRepositoryInterface using data imported by OcreviewApiDataImporter
 */
class ApiOpenChatPageRepository implements OpenChatPageRepositoryInterface
{
    /**
     * Get OpenChat data by ID
     * 
     * @param int $id OpenChat ID
     * @return array|false OpenChat data or false if not found
     */
    function getOpenChatById(int $id): array|false
    {
        $query =
            "SELECT
                om.openchat_id AS id,
                om.line_internal_id AS emid,
                om.display_name AS name,
                om.invitation_url AS url,
                om.description,
                '' AS img_url,
                om.profile_image_url AS api_img_url,
                om.current_member_count AS member,
                CASE 
                    WHEN om.verification_badge = 'スペシャル' THEN 1
                    WHEN om.verification_badge = '公式認証' THEN 2
                    ELSE NULL
                END AS emblem,
                om.category_id AS category,
                CASE om.join_method
                    WHEN '全体公開' THEN 0
                    WHEN '参加承認制' THEN 1
                    WHEN '参加コード入力制' THEN 2
                    ELSE 0
                END AS join_method_type,
                UNIX_TIMESTAMP(om.established_at) AS api_created_at,
                om.first_seen_at AS created_at,
                om.last_updated_at AS updated_at,
                NULL AS tag1,
                NULL AS tag2,
                NULL AS tag3,
                COALESCE(grh.member_increase_count, 0) AS rh_diff_member,
                COALESCE(grh.growth_rate_percent, 0) AS rh_percent_increase,
                COALESCE(grd.member_increase_count, 0) AS rd_diff_member,
                COALESCE(grd.growth_rate_percent, 0) AS rd_percent_increase,
                COALESCE(grw.member_increase_count, 0) AS rw_diff_member,
                COALESCE(grw.growth_rate_percent, 0) AS rw_percent_increase
            FROM 
                openchat_master om
            LEFT JOIN 
                growth_ranking_past_hour grh ON om.openchat_id = grh.openchat_id
            LEFT JOIN 
                growth_ranking_past_24_hours grd ON om.openchat_id = grd.openchat_id
            LEFT JOIN 
                growth_ranking_past_week grw ON om.openchat_id = grw.openchat_id
            WHERE 
                om.openchat_id = :id";

        return ApiDB::fetch($query, compact('id'));
    }

    /**
     * Get OpenChat data by ID with tag information
     * Note: Tags are not available in ocgraph_sqlapi database, returning same as getOpenChatById
     * 
     * @param int $id OpenChat ID
     * @return array|false OpenChat data or false if not found
     */
    function getOpenChatByIdWithTag(int $id): array|false
    {
        // In API database, tags are not available, so we return the same as getOpenChatById
        // Tags will be NULL in the result
        return $this->getOpenChatById($id);
    }

    /**
     * Check if OpenChat exists
     * 
     * @param int $id OpenChat ID
     * @return bool True if exists, false otherwise
     */
    function isExistsOpenChat(int $id): bool
    {
        return (bool) ApiDB::fetchColumn(
            "SELECT 1 FROM openchat_master WHERE openchat_id = :id LIMIT 1",
            compact('id')
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Repositories\Api;

class ApiDeletedOpenChatListRepository
{
    /**
     * Filter patterns for display_name
     * Each element can be:
     * - ['pattern' => 'regex', 'categories' => null] - applies to all categories
     * - ['pattern' => 'regex', 'categories' => ['category_name1', 'category_name2']] - applies only to specified categories
     */
    private static array $filterPatterns = [
        ['pattern' => '/第[0-9０-９]+回/', 'categories' => null], // 第{半角または全角の数字１文字以上}回
        ['pattern' => '/大学/', 'categories' => null], // 大学
        ['pattern' => '/受験/', 'categories' => null], // 受験
        ['pattern' => '/会/', 'categories' => ['スポーツ', '団体']], // カテゴリがスポーツまたは団体で「会」が含まれる
        ['pattern' => '/同窓会/', 'categories' => null], // 同窓会
        ['pattern' => '/周年/', 'categories' => null],
        ['pattern' => '/内定者/', 'categories' => null],
        ['pattern' => '/会員/', 'categories' => null],
        ['pattern' => '/日間/', 'categories' => null],
        ['pattern' => '/会場/', 'categories' => null],
        ['pattern' => '/忘年会/', 'categories' => null],
        ['pattern' => '/教員/', 'categories' => null],
        ['pattern' => '/就活/', 'categories' => null],
        ['pattern' => '/連絡用/', 'categories' => null],
    ];

    function getDeletedOpenChatList(string $date, int $limit): array|false
    {
        $query =
            "SELECT
                om.openchat_id,
                om.category_id,
                ocd.deleted_at,
                om.display_name,
                om.current_member_count,
                c.category_name,
                om.description,
                om.verification_badge,
                om.join_method,
                om.established_at,
                om.invitation_url,
                om.profile_image_url,
                om.line_internal_id
            FROM
                openchat_master om
            JOIN
                ocgraph_ocreview.open_chat_deleted ocd ON om.openchat_id = ocd.id
            JOIN
                categories c ON om.category_id = c.category_id
            WHERE
                DATE(ocd.deleted_at) = :date
                AND om.current_member_count >= 15
            ORDER BY
                om.established_at DESC";

        $deletedOpenChats = ApiDB::fetchAll($query, compact('date'));

        if ($deletedOpenChats === false) {
            return false;
        }

        // Filter out OpenChats based on display_name patterns
        $deletedOpenChats = array_filter($deletedOpenChats, function($openChat) {
            foreach (self::$filterPatterns as $filter) {
                // Check if pattern matches
                if (preg_match($filter['pattern'], $openChat['display_name'])) {
                    // If categories are specified, check if current category matches
                    if ($filter['categories'] === null) {
                        // No category restriction, filter out
                        return false;
                    } elseif (in_array($openChat['category_name'], $filter['categories'])) {
                        // Category matches, filter out
                        return false;
                    }
                }
            }
            return true;
        });

        // Re-index array after filtering
        $deletedOpenChats = array_values($deletedOpenChats);

        // Fetch latest ranking for each OpenChat
        foreach ($deletedOpenChats as &$openChat) {
            $rankingQuery =
                "SELECT 
                    activity_ranking_position
                FROM 
                    line_official_activity_ranking_history
                WHERE
                    openchat_id = :openchat_id
                    AND category_id = :category_id
                ORDER BY 
                    record_date DESC, 
                    record_id DESC
                LIMIT 1";

            $ranking = ApiDB::fetch($rankingQuery, [
                'openchat_id' => $openChat['openchat_id'],
                'category_id' => $openChat['category_id'],
            ]);

            $openChat['activity_ranking_position'] = $ranking['activity_ranking_position'] ?? null;

            // Remove category_id from the final result as it was not in the original output
            unset($openChat['category_id']);
        }

        // Sort by activity_ranking_position
        usort($deletedOpenChats, function ($a, $b) {
            $posA = $a['activity_ranking_position'] ?? 999999;
            $posB = $b['activity_ranking_position'] ?? 999999;

            if ($posA === $posB) {
                return $a['openchat_id'] <=> $b['openchat_id'];
            }

            return $posA <=> $posB;
        });

        return array_slice($deletedOpenChats, 0, $limit);
    }
}

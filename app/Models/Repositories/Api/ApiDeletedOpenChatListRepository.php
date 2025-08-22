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
        ['pattern' => '/(?<![0-9０-９])[0-9０-９]{1,2}[\/／][0-9０-９]{1,2](?![0-9０-９])/', 'categories' => null],
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
                AND om.current_member_count > 10
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

        // Fetch member growth statistics for each OpenChat
        foreach ($deletedOpenChats as &$openChat) {
            // Fetch latest member count and oldest available count (preferably 7 days ago or older)
            $growthQuery =
                "SELECT 
                    latest.member_count as latest_count,
                    latest.statistics_date as latest_date,
                    COALESCE(week_ago.member_count, oldest.member_count) as comparison_count,
                    COALESCE(week_ago.statistics_date, oldest.statistics_date) as comparison_date
                FROM 
                    (SELECT member_count, statistics_date 
                     FROM daily_member_statistics 
                     WHERE openchat_id = :openchat_id 
                     ORDER BY statistics_date DESC 
                     LIMIT 1) as latest
                LEFT JOIN
                    (SELECT member_count, statistics_date 
                     FROM daily_member_statistics 
                     WHERE openchat_id = :openchat_id2 
                       AND statistics_date <= DATE_SUB(
                           (SELECT MAX(statistics_date) FROM daily_member_statistics WHERE openchat_id = :openchat_id3),
                           INTERVAL 7 DAY
                       )
                     ORDER BY statistics_date DESC 
                     LIMIT 1) as week_ago ON 1=1
                LEFT JOIN
                    (SELECT member_count, statistics_date 
                     FROM daily_member_statistics 
                     WHERE openchat_id = :openchat_id4
                     ORDER BY statistics_date ASC 
                     LIMIT 1) as oldest ON 1=1";

            $growth = ApiDB::fetch($growthQuery, [
                'openchat_id' => $openChat['openchat_id'],
                'openchat_id2' => $openChat['openchat_id'],
                'openchat_id3' => $openChat['openchat_id'],
                'openchat_id4' => $openChat['openchat_id'],
            ]);

            // Calculate member growth
            if ($growth && $growth['latest_count'] !== null && $growth['comparison_count'] !== null) {
                // If we have both latest and comparison data, calculate the difference
                if ($growth['latest_date'] !== $growth['comparison_date']) {
                    $openChat['member_growth'] = $growth['latest_count'] - $growth['comparison_count'];
                } else {
                    // If only one record exists, set growth to 0
                    $openChat['member_growth'] = 0;
                }
            } else {
                // If no data available, set growth to 0
                $openChat['member_growth'] = 0;
            }

            // Remove category_id from the final result
            unset($openChat['category_id']);
        }

        // Sort by member growth (descending order)
        usort($deletedOpenChats, function ($a, $b) {
            $growthA = $a['member_growth'] ?? 0;
            $growthB = $b['member_growth'] ?? 0;

            if ($growthA === $growthB) {
                // If growth is the same, sort by openchat_id for consistency
                return $a['openchat_id'] <=> $b['openchat_id'];
            }

            // Sort in descending order (higher growth first)
            return $growthB <=> $growthA;
        });

        // Apply boost for specific keywords and member count
        $boostedKeywords = ['大人', 'シングル'];
        $boostedKeywordsInNameOrDesc = ['リア友', '友達'];
        
        // Find and categorize items by boost priority
        $highPriorityItems = [];  // Keyword matches - boost to top 20
        $mediumPriorityItems = []; // 50+ members - boost to top 48
        $regularItems = [];
        
        foreach ($deletedOpenChats as $openChat) {
            $hasKeyword = false;
            
            // Check display_name for first set of keywords
            foreach ($boostedKeywords as $keyword) {
                if (mb_strpos($openChat['display_name'], $keyword) !== false) {
                    $hasKeyword = true;
                    break;
                }
            }
            
            // Check display_name and description for second set of keywords
            if (!$hasKeyword) {
                foreach ($boostedKeywordsInNameOrDesc as $keyword) {
                    if (mb_strpos($openChat['display_name'], $keyword) !== false || 
                        mb_strpos($openChat['description'], $keyword) !== false) {
                        $hasKeyword = true;
                        break;
                    }
                }
            }
            
            if ($hasKeyword) {
                $highPriorityItems[] = $openChat;
            } elseif ($openChat['current_member_count'] >= 50) {
                $mediumPriorityItems[] = $openChat;
            } else {
                $regularItems[] = $openChat;
            }
        }
        
        // Merge results with natural distribution
        $finalResult = [];
        $highIndex = 0;
        $mediumIndex = 0;
        $regularIndex = 0;
        
        // Natural positions for high priority items within top 20
        $highPriorityPositions = [2, 5, 8, 11, 14, 17, 19];
        // Natural positions for medium priority items within positions 20-48
        $mediumPriorityPositions = [22, 25, 28, 31, 34, 37, 40, 43, 46];
        
        for ($i = 0; $i < count($deletedOpenChats); $i++) {
            // Insert high priority items at specific positions within top 20
            if ($i < 20 && in_array($i, $highPriorityPositions) && $highIndex < count($highPriorityItems)) {
                $finalResult[] = $highPriorityItems[$highIndex++];
            }
            // Insert medium priority items at specific positions within 20-48
            elseif ($i >= 20 && $i < 48 && in_array($i, $mediumPriorityPositions) && $mediumIndex < count($mediumPriorityItems)) {
                $finalResult[] = $mediumPriorityItems[$mediumIndex++];
            }
            // Fill with regular items
            elseif ($regularIndex < count($regularItems)) {
                $finalResult[] = $regularItems[$regularIndex++];
            }
            // Add remaining high priority items if regular items are exhausted
            elseif ($highIndex < count($highPriorityItems)) {
                $finalResult[] = $highPriorityItems[$highIndex++];
            }
            // Add remaining medium priority items if other items are exhausted
            elseif ($mediumIndex < count($mediumPriorityItems)) {
                $finalResult[] = $mediumPriorityItems[$mediumIndex++];
            }
        }
        
        $deletedOpenChats = $finalResult;

        // Remove the temporary member_growth field from the results
        foreach ($deletedOpenChats as &$openChat) {
            unset($openChat['member_growth']);
        }

        return array_slice($deletedOpenChats, 0, $limit);
    }
}

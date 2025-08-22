<?php

declare(strict_types=1);

namespace App\Models\Repositories\Api;

class ApiDeletedOpenChatListRepository
{
    /**
     * display_nameのフィルターパターン
     * 各要素は以下の形式:
     * - ['pattern' => '正規表現', 'categories' => null] - 全カテゴリに適用
     * - ['pattern' => '正規表現', 'categories' => ['カテゴリ名1', 'カテゴリ名2']] - 指定カテゴリのみに適用
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
        ['pattern' => '/^[0-9０-９]{1,2}[\/／][0-9０-９]{1,2}/', 'categories' => null],  // 先頭の日付形式（8/20など）
    ];

    /**
     * 高優先度キーワード（上位20位以内に押し上げ）
     * display_nameにこれらのキーワードを含むルームを優先表示
     */
    private const HIGH_PRIORITY_KEYWORDS_NAME = ['大人', 'シングル'];

    /**
     * 高優先度キーワード（上位20位以内に押し上げ）
     * display_nameまたはdescriptionにこれらのキーワードを含むルームを優先表示
     */
    private const HIGH_PRIORITY_KEYWORDS_NAME_OR_DESC = ['リア友', '友達'];

    /**
     * 低優先度キーワード（順位を下げる）
     * display_nameにこれらのキーワードを含むルームは下位に配置
     */
    private const LOW_PRIORITY_KEYWORDS = ['也', 'なりきり', 'nrkr', 'オリキャラ', 'ﾅﾘｷﾘ', 'LOW_PRIORITY_KEYWORDS'];

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
            // 最新のメンバー数、比較用メンバー数、最大・最小メンバー数を取得
            $growthQuery =
                "SELECT 
                    latest.member_count as latest_count,
                    latest.statistics_date as latest_date,
                    COALESCE(
                        week_ago_or_newer.member_count,
                        oldest.member_count
                    ) as comparison_count,
                    COALESCE(
                        week_ago_or_newer.statistics_date,
                        oldest.statistics_date
                    ) as comparison_date,
                    peak.member_count as peak_count,
                    valley.member_count as valley_count
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
                     LIMIT 1) as week_ago_or_newer ON 1=1
                LEFT JOIN
                    (SELECT member_count, statistics_date 
                     FROM daily_member_statistics 
                     WHERE openchat_id = :openchat_id4
                     ORDER BY statistics_date ASC 
                     LIMIT 1) as oldest ON 1=1
                LEFT JOIN
                    (SELECT MAX(member_count) as member_count
                     FROM daily_member_statistics 
                     WHERE openchat_id = :openchat_id5) as peak ON 1=1
                LEFT JOIN
                    (SELECT MIN(member_count) as member_count
                     FROM daily_member_statistics 
                     WHERE openchat_id = :openchat_id6) as valley ON 1=1";

            $growth = ApiDB::fetch($growthQuery, [
                'openchat_id' => $openChat['openchat_id'],
                'openchat_id2' => $openChat['openchat_id'],
                'openchat_id3' => $openChat['openchat_id'],
                'openchat_id4' => $openChat['openchat_id'],
                'openchat_id5' => $openChat['openchat_id'],
                'openchat_id6' => $openChat['openchat_id'],
            ]);

            // メンバー増加数を計算
            if ($growth && $growth['latest_count'] !== null && $growth['comparison_count'] !== null) {
                // 最新と比較データの両方がある場合、差分を計算
                if ($growth['latest_date'] !== $growth['comparison_date']) {
                    $openChat['member_growth'] = $growth['latest_count'] - $growth['comparison_count'];
                } else {
                    // レコードが1つしかない場合、増加数を0に設定
                    $openChat['member_growth'] = 0;
                }
            } else {
                // データがない場合、増加数を0に設定
                $openChat['member_growth'] = 0;
            }
            
            // 最大メンバー数からの減少率と最小メンバー数からの成長率を計算
            $openChat['peak_decline_rate'] = 0;
            $openChat['valley_growth_rate'] = 0;
            
            if ($growth && $growth['latest_count'] !== null) {
                // 最大メンバー数からの減少率
                if ($growth['peak_count'] !== null && $growth['peak_count'] > 0) {
                    $decline = $growth['peak_count'] - $growth['latest_count'];
                    if ($decline > 0) {
                        $openChat['peak_decline_rate'] = ($decline / $growth['peak_count']) * 100;
                    }
                }
                
                // 最小メンバー数からの成長率
                if ($growth['valley_count'] !== null && $growth['valley_count'] > 0) {
                    $growthAmount = $growth['latest_count'] - $growth['valley_count'];
                    if ($growthAmount > 0) {
                        $openChat['valley_growth_rate'] = ($growthAmount / $growth['valley_count']) * 100;
                    }
                }
            }

            // 最終結果からcategory_idを削除
            unset($openChat['category_id']);
        }

        // 最初に新しいロジックでソート
        usort($deletedOpenChats, function ($a, $b) {
            // 高優先度キーワードのチェック
            $hasHighPriorityA = false;
            $hasHighPriorityB = false;
            
            // display_nameで高優先度キーワード（第1グループ）をチェック
            foreach (self::HIGH_PRIORITY_KEYWORDS_NAME as $keyword) {
                if (mb_strpos($a['display_name'], $keyword) !== false) {
                    $hasHighPriorityA = true;
                    break;
                }
            }
            foreach (self::HIGH_PRIORITY_KEYWORDS_NAME as $keyword) {
                if (mb_strpos($b['display_name'], $keyword) !== false) {
                    $hasHighPriorityB = true;
                    break;
                }
            }
            
            // display_nameとdescriptionで高優先度キーワード（第2グループ）をチェック
            if (!$hasHighPriorityA) {
                foreach (self::HIGH_PRIORITY_KEYWORDS_NAME_OR_DESC as $keyword) {
                    if (mb_strpos($a['display_name'], $keyword) !== false || 
                        mb_strpos($a['description'], $keyword) !== false) {
                        $hasHighPriorityA = true;
                        break;
                    }
                }
            }
            if (!$hasHighPriorityB) {
                foreach (self::HIGH_PRIORITY_KEYWORDS_NAME_OR_DESC as $keyword) {
                    if (mb_strpos($b['display_name'], $keyword) !== false || 
                        mb_strpos($b['description'], $keyword) !== false) {
                        $hasHighPriorityB = true;
                        break;
                    }
                }
            }
            
            // 第0優先: 高優先度キーワードの有無
            if ($hasHighPriorityA !== $hasHighPriorityB) {
                return $hasHighPriorityB <=> $hasHighPriorityA; // 高優先度が上位
            }
            
            $currentMemberA = $a['current_member_count'] ?? 0;
            $currentMemberB = $b['current_member_count'] ?? 0;
            $declineA = $a['peak_decline_rate'] ?? 0;
            $declineB = $b['peak_decline_rate'] ?? 0;
            $memberGrowthA = $a['member_growth'] ?? 0;
            $memberGrowthB = $b['member_growth'] ?? 0;
            
            // 第1優先: 大幅減少ペナルティ（30%以上減少は下位）
            $severePenaltyA = $declineA >= 30;
            $severePenaltyB = $declineB >= 30;
            
            if ($severePenaltyA !== $severePenaltyB) {
                return $severePenaltyA <=> $severePenaltyB; // 大幅減少していない方が上位
            }
            
            // 低優先度キーワードのチェック
            $hasLowPriorityA = false;
            $hasLowPriorityB = false;
            
            foreach (self::LOW_PRIORITY_KEYWORDS as $keyword) {
                if (mb_strpos($a['display_name'], $keyword) !== false) {
                    $hasLowPriorityA = true;
                    break;
                }
            }
            foreach (self::LOW_PRIORITY_KEYWORDS as $keyword) {
                if (mb_strpos($b['display_name'], $keyword) !== false) {
                    $hasLowPriorityB = true;
                    break;
                }
            }
            
            // 第2優先: 低優先度キーワードペナルティ
            if ($hasLowPriorityA !== $hasLowPriorityB) {
                return $hasLowPriorityA <=> $hasLowPriorityB; // 低優先度でない方が上位
            }
            
            // 第3優先: 小規模ルーム（20人以下）の大幅ペナルティ（高優先度キーワードは除外）
            $smallRoomPenaltyA = ($currentMemberA <= 20 && !$hasHighPriorityA);
            $smallRoomPenaltyB = ($currentMemberB <= 20 && !$hasHighPriorityB);
            
            if ($smallRoomPenaltyA !== $smallRoomPenaltyB) {
                return $smallRoomPenaltyA <=> $smallRoomPenaltyB; // 小規模でない方が上位
            }
            
            // 第4優先: メンバー増加数（多い順）
            if ($memberGrowthA !== $memberGrowthB) {
                return $memberGrowthB <=> $memberGrowthA;
            }
            
            // 第5優先: 現在のメンバー数（多い順）
            if ($currentMemberA !== $currentMemberB) {
                return $currentMemberB <=> $currentMemberA;
            }

            return $a['openchat_id'] <=> $b['openchat_id'];
        });

        // 特定キーワードとメンバー数による優先度調整
        
        // 優先度別にアイテムを分類  
        $highPriorityItems = [];  // キーワード一致 - 上位20位以内に押し上げ
        $mediumPriorityItems = []; // メンバー50人以上 - 上位48位以内に押し上げ
        $regularItems = [];
        $lowPriorityItems = [];   // 低優先度キーワードまたは減少中 - 10位程度下げる
        
        foreach ($deletedOpenChats as $openChat) {
            $hasHighPriorityKeyword = false;
            $hasLowPriorityKeyword = false;
            $valleyGrowthRate = $openChat['valley_growth_rate'] ?? 0;
            
            // display_nameで高優先度キーワード（第1グループ）をチェック
            foreach (self::HIGH_PRIORITY_KEYWORDS_NAME as $keyword) {
                if (mb_strpos($openChat['display_name'], $keyword) !== false) {
                    $hasHighPriorityKeyword = true;
                    break;
                }
            }
            
            // display_nameとdescriptionで高優先度キーワード（第2グループ）をチェック
            if (!$hasHighPriorityKeyword) {
                foreach (self::HIGH_PRIORITY_KEYWORDS_NAME_OR_DESC as $keyword) {
                    if (mb_strpos($openChat['display_name'], $keyword) !== false || 
                        mb_strpos($openChat['description'], $keyword) !== false) {
                        $hasHighPriorityKeyword = true;
                        break;
                    }
                }
            }
            
            // display_nameで低優先度キーワードをチェック
            if (!$hasHighPriorityKeyword) {
                foreach (self::LOW_PRIORITY_KEYWORDS as $keyword) {
                    if (mb_strpos($openChat['display_name'], $keyword) !== false) {
                        $hasLowPriorityKeyword = true;
                        break;
                    }
                }
            }
            
            // 分類（最低から大きく成長しているルームは高優先度扱い）
            if ($hasHighPriorityKeyword || $valleyGrowthRate >= 100) {
                // キーワード一致または最低から2倍以上成長している場合は高優先度
                $highPriorityItems[] = $openChat;
            } elseif ($hasLowPriorityKeyword) {
                $lowPriorityItems[] = $openChat;
            } elseif ($openChat['current_member_count'] >= 50 || $valleyGrowthRate >= 50) {
                // メンバー50人以上または最低から1.5倍以上成長している場合は中優先度
                $mediumPriorityItems[] = $openChat;
            } else {
                $regularItems[] = $openChat;
            }
        }
        
        // 高優先度キーワードを最優先にしたソート関数
        $sortWithHighPriorityFirst = function($a, $b) {
            // 高優先度キーワードのチェック
            $hasHighPriorityA = false;
            $hasHighPriorityB = false;
            
            // display_nameで高優先度キーワード（第1グループ）をチェック
            foreach (self::HIGH_PRIORITY_KEYWORDS_NAME as $keyword) {
                if (mb_strpos($a['display_name'], $keyword) !== false) {
                    $hasHighPriorityA = true;
                    break;
                }
            }
            foreach (self::HIGH_PRIORITY_KEYWORDS_NAME as $keyword) {
                if (mb_strpos($b['display_name'], $keyword) !== false) {
                    $hasHighPriorityB = true;
                    break;
                }
            }
            
            // display_nameとdescriptionで高優先度キーワード（第2グループ）をチェック
            if (!$hasHighPriorityA) {
                foreach (self::HIGH_PRIORITY_KEYWORDS_NAME_OR_DESC as $keyword) {
                    if (mb_strpos($a['display_name'], $keyword) !== false || 
                        mb_strpos($a['description'], $keyword) !== false) {
                        $hasHighPriorityA = true;
                        break;
                    }
                }
            }
            if (!$hasHighPriorityB) {
                foreach (self::HIGH_PRIORITY_KEYWORDS_NAME_OR_DESC as $keyword) {
                    if (mb_strpos($b['display_name'], $keyword) !== false || 
                        mb_strpos($b['description'], $keyword) !== false) {
                        $hasHighPriorityB = true;
                        break;
                    }
                }
            }
            
            // 第0優先: 高優先度キーワードの有無
            if ($hasHighPriorityA !== $hasHighPriorityB) {
                return $hasHighPriorityB <=> $hasHighPriorityA; // 高優先度が上位
            }
            
            // 低優先度キーワードのチェック
            $hasLowPriorityA = false;
            $hasLowPriorityB = false;
            
            foreach (self::LOW_PRIORITY_KEYWORDS as $keyword) {
                if (mb_strpos($a['display_name'], $keyword) !== false) {
                    $hasLowPriorityA = true;
                    break;
                }
            }
            foreach (self::LOW_PRIORITY_KEYWORDS as $keyword) {
                if (mb_strpos($b['display_name'], $keyword) !== false) {
                    $hasLowPriorityB = true;
                    break;
                }
            }
            
            $currentMemberA = $a['current_member_count'] ?? 0;
            $currentMemberB = $b['current_member_count'] ?? 0;
            $memberGrowthA = $a['member_growth'] ?? 0;
            $memberGrowthB = $b['member_growth'] ?? 0;
            $growthA = $a['valley_growth_rate'] ?? 0;
            $growthB = $b['valley_growth_rate'] ?? 0;
            $declineA = $a['peak_decline_rate'] ?? 0;
            $declineB = $b['peak_decline_rate'] ?? 0;
            
            // 大幅減少（30%以上）の場合は大きくペナルティ
            $severePenaltyA = $declineA >= 30;
            $severePenaltyB = $declineB >= 30;
            
            // 第1優先: 大幅減少ペナルティ（30%以上減少は下位）
            if ($severePenaltyA !== $severePenaltyB) {
                return $severePenaltyA <=> $severePenaltyB; // 大幅減少していない方が上位
            }
            
            // 第2優先: 低優先度キーワードペナルティ
            if ($hasLowPriorityA !== $hasLowPriorityB) {
                return $hasLowPriorityA <=> $hasLowPriorityB; // 低優先度でない方が上位
            }
            
            // 第3優先: メンバー増加数（多い順）
            if ($memberGrowthA !== $memberGrowthB) {
                return $memberGrowthB <=> $memberGrowthA;
            }
            
            // 第4優先: 小規模ルーム（20人以下）の大幅ペナルティ（高優先度キーワードは除外）
            $smallRoomPenaltyA = ($currentMemberA <= 20 && !$hasHighPriorityA);
            $smallRoomPenaltyB = ($currentMemberB <= 20 && !$hasHighPriorityB);
            
            if ($smallRoomPenaltyA !== $smallRoomPenaltyB) {
                return $smallRoomPenaltyA <=> $smallRoomPenaltyB; // 小規模でない方が上位
            }
            
            // 第5優先: 現在のメンバー数（多い順）
            if ($currentMemberA !== $currentMemberB) {
                return $currentMemberB <=> $currentMemberA;
            }
            
            // 第6優先: 成長率（高い順）
            if ($growthA !== $growthB) {
                return $growthB <=> $growthA;
            }
            
            // 第7優先: 減少率（低い順）
            if ($declineA !== $declineB) {
                return $declineA <=> $declineB;
            }
            
            return $a['openchat_id'] <=> $b['openchat_id'];
        };
        
        usort($regularItems, $sortWithHighPriorityFirst);
        usort($lowPriorityItems, $sortWithHighPriorityFirst);
        usort($mediumPriorityItems, $sortWithHighPriorityFirst);
        usort($highPriorityItems, $sortWithHighPriorityFirst);
        
        // 自然な分布で結果をマージ
        $finalResult = [];
        $highIndex = 0;
        $mediumIndex = 0;
        $regularIndex = 0;
        $lowIndex = 0;
        
        // 上位20位以内での高優先度アイテムの自然な配置位置
        $highPriorityPositions = [2, 5, 8, 11, 14, 17, 19];
        // 20-48位での中優先度アイテムの自然な配置位置
        $mediumPriorityPositions = [22, 25, 28, 31, 34, 37, 40, 43, 46];
        
        // まず高・中・通常優先度のアイテムをマージ
        for ($i = 0; $i < count($deletedOpenChats); $i++) {
            // 上位20位以内の特定位置に高優先度アイテムを挿入
            if ($i < 20 && in_array($i, $highPriorityPositions) && $highIndex < count($highPriorityItems)) {
                $finalResult[] = $highPriorityItems[$highIndex++];
            }
            // 20-48位の特定位置に中優先度アイテムを挿入
            elseif ($i >= 20 && $i < 48 && in_array($i, $mediumPriorityPositions) && $mediumIndex < count($mediumPriorityItems)) {
                $finalResult[] = $mediumPriorityItems[$mediumIndex++];
            }
            // 通常アイテムで埋める
            elseif ($regularIndex < count($regularItems)) {
                $finalResult[] = $regularItems[$regularIndex++];
            }
            // 通常アイテムがなくなったら残りの高優先度アイテムを追加
            elseif ($highIndex < count($highPriorityItems)) {
                $finalResult[] = $highPriorityItems[$highIndex++];
            }
            // 他のアイテムがなくなったら残りの中優先度アイテムを追加
            elseif ($mediumIndex < count($mediumPriorityItems)) {
                $finalResult[] = $mediumPriorityItems[$mediumIndex++];
            }
        }
        
        // 低優先度アイテムを10位程度下げて挿入
        $adjustedResult = [];
        $insertedCount = 0;
        
        foreach ($finalResult as $item) {
            $adjustedResult[] = $item;
            $insertedCount++;
            
            // 10個ごとに低優先度アイテムを1つ挿入
            if ($insertedCount % 10 === 0 && $lowIndex < count($lowPriorityItems)) {
                $adjustedResult[] = $lowPriorityItems[$lowIndex++];
            }
        }
        
        // 残りの低優先度アイテムを最後に追加
        while ($lowIndex < count($lowPriorityItems)) {
            $adjustedResult[] = $lowPriorityItems[$lowIndex++];
        }
        
        $deletedOpenChats = $adjustedResult;

        // 結果から一時的なフィールドを削除
        foreach ($deletedOpenChats as &$openChat) {
            unset($openChat['member_growth']);
            unset($openChat['peak_decline_rate']);
            unset($openChat['valley_growth_rate']);
        }

        return array_slice($deletedOpenChats, 0, $limit);
    }
}

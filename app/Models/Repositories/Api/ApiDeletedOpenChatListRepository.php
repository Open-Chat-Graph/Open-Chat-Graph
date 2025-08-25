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
        ['pattern' => '/志望者/', 'categories' => null],
        ['pattern' => '/連絡用/', 'categories' => null],
        ['pattern' => '/[0-9０-９]{1,2}[\/／][0-9０-９]{1,2}/', 'categories' => null],  // 先頭の日付形式（8/20など）
        ['pattern' => '/[0-9０-９]{1,2}月[0-9０-９]{1,2}日/', 'categories' => null],  // x月x日,xx月xxx日
    ];

    /**
     * 高優先度キーワード（上位20位以内に押し上げ）
     * display_nameにこれらのキーワードを含むルームを優先表示
     */
    private const HIGH_PRIORITY_KEYWORDS_NAME = ['その先', '40代', '50代', 'シングル'];

    /**
     * 高優先度キーワード（上位20位以内に押し上げ）
     * display_nameまたはdescriptionにこれらのキーワードを含むルームを優先表示
     */
    private const HIGH_PRIORITY_KEYWORDS_NAME_OR_DESC = [];

    /**
     * 低優先度キーワード（順位を下げる）
     * display_nameにこれらのキーワードを含むルームは下位に配置
     */
    private const LOW_PRIORITY_KEYWORDS = [];

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
                AND EXISTS (
                    SELECT 1
                    FROM daily_member_statistics dms1
                    WHERE dms1.openchat_id = om.openchat_id
                    AND dms1.statistics_date = DATE_SUB(
                        (SELECT MAX(statistics_date)
                         FROM daily_member_statistics dms2
                         WHERE dms2.openchat_id = om.openchat_id),
                        INTERVAL 1 DAY
                    )
                )
            ORDER BY
                om.established_at DESC";

        $deletedOpenChats = ApiDB::fetchAll($query, compact('date'));

        if ($deletedOpenChats === false) {
            return false;
        }

        // Filter out OpenChats based on display_name patterns
        $deletedOpenChats = array_filter($deletedOpenChats, function ($openChat) {
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
            // 比較用メンバー数、最大・最小メンバー数、1ヶ月前のデータを取得（最新は current_member_count を使用）
            $growthQuery =
                "SELECT 
                    COALESCE(
                        week_ago_or_newer.member_count,
                        oldest.member_count
                    ) as comparison_count,
                    COALESCE(
                        week_ago_or_newer.statistics_date,
                        oldest.statistics_date
                    ) as comparison_date,
                    month_ago.member_count as month_ago_count,
                    month_ago.statistics_date as month_ago_date,
                    peak.member_count as peak_count,
                    valley.member_count as valley_count,
                    oldest.statistics_date as oldest_date
                FROM 
                    (SELECT member_count, statistics_date 
                     FROM daily_member_statistics 
                     WHERE openchat_id = :openchat_id 
                       AND statistics_date <= DATE_SUB(NOW(), INTERVAL 7 DAY)
                     ORDER BY statistics_date DESC 
                     LIMIT 1) as week_ago_or_newer
                LEFT JOIN
                    (SELECT member_count, statistics_date 
                     FROM daily_member_statistics 
                     WHERE openchat_id = :openchat_id2
                     ORDER BY statistics_date ASC 
                     LIMIT 1) as oldest ON 1=1
                LEFT JOIN
                    (SELECT member_count, statistics_date 
                     FROM daily_member_statistics 
                     WHERE openchat_id = :openchat_id3
                       AND statistics_date <= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     ORDER BY statistics_date DESC 
                     LIMIT 1) as month_ago ON 1=1
                LEFT JOIN
                    (SELECT MAX(member_count) as member_count
                     FROM daily_member_statistics 
                     WHERE openchat_id = :openchat_id4) as peak ON 1=1
                LEFT JOIN
                    (SELECT MIN(member_count) as member_count
                     FROM daily_member_statistics 
                     WHERE openchat_id = :openchat_id5) as valley ON 1=1";

            $growth = ApiDB::fetch($growthQuery, [
                'openchat_id' => $openChat['openchat_id'],
                'openchat_id2' => $openChat['openchat_id'],
                'openchat_id3' => $openChat['openchat_id'],
                'openchat_id4' => $openChat['openchat_id'],
                'openchat_id5' => $openChat['openchat_id'],
            ]);

            // メンバー増加数を計算（最新はcurrent_member_countを使用）
            $currentMemberCount = $openChat['current_member_count'];
            if ($growth && $growth['comparison_count'] !== null) {
                // 比較データがある場合、current_member_countとの差分を計算
                $openChat['member_growth'] = $currentMemberCount - $growth['comparison_count'];
                // 比較データの日付を保存（1ヶ月以上前かチェック用）
                $openChat['comparison_date'] = $growth['comparison_date'];
            } else {
                // データがない場合、増加数を0に設定
                $openChat['member_growth'] = 0;
                $openChat['comparison_date'] = null;
            }
            
            // 1ヶ月以上メンバー数の増加が0かどうかをチェック
            $openChat['zero_growth_over_month'] = false;
            if ($growth) {
                if ($growth['month_ago_count'] !== null) {
                    // 1ヶ月前のデータが存在する場合
                    if ($currentMemberCount <= $growth['month_ago_count']) {
                        $openChat['zero_growth_over_month'] = true;
                    }
                } else if ($growth['oldest_date'] !== null) {
                    // 1ヶ月前のデータが存在しない場合、最古のデータを確認
                    $oldestDate = new \DateTime($growth['oldest_date']);
                    $now = new \DateTime();
                    $interval = $oldestDate->diff($now);
                    
                    if ($interval->days <= 30) {
                        // 最古のデータが30日以内の場合（新しいルーム）
                        if ($growth['comparison_count'] !== null && $currentMemberCount <= $growth['comparison_count']) {
                            $openChat['zero_growth_over_month'] = true;
                        }
                    } else {
                        // 最古のデータが30日より前の場合（レコードが歯抜けで稼働率が低い）
                        // 大幅ダウン対象とする
                        $openChat['zero_growth_over_month'] = true;
                    }
                }
            }

            // 最大メンバー数からの減少率と最小メンバー数からの成長率を計算（最新はcurrent_member_countを使用）
            $openChat['peak_decline_rate'] = 0;
            $openChat['valley_growth_rate'] = 0;

            if ($growth) {
                // 最大メンバー数からの減少率
                if ($growth['peak_count'] !== null && $growth['peak_count'] > 0) {
                    $decline = $growth['peak_count'] - $currentMemberCount;
                    if ($decline > 0) {
                        $openChat['peak_decline_rate'] = ($decline / $growth['peak_count']) * 100;
                    }
                }

                // 最小メンバー数からの成長率
                if ($growth['valley_count'] !== null && $growth['valley_count'] > 0) {
                    $growthAmount = $currentMemberCount - $growth['valley_count'];
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
                    if (
                        mb_strpos($a['display_name'], $keyword) !== false ||
                        mb_strpos($a['description'], $keyword) !== false
                    ) {
                        $hasHighPriorityA = true;
                        break;
                    }
                }
            }
            if (!$hasHighPriorityB) {
                foreach (self::HIGH_PRIORITY_KEYWORDS_NAME_OR_DESC as $keyword) {
                    if (
                        mb_strpos($b['display_name'], $keyword) !== false ||
                        mb_strpos($b['description'], $keyword) !== false
                    ) {
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

            // 第1優先: 1ヶ月以上メンバー数の増加が0のルームは大幅にダウン
            $zeroGrowthOverMonthA = $a['zero_growth_over_month'] ?? false;
            $zeroGrowthOverMonthB = $b['zero_growth_over_month'] ?? false;
            
            if ($zeroGrowthOverMonthA !== $zeroGrowthOverMonthB) {
                return $zeroGrowthOverMonthA <=> $zeroGrowthOverMonthB; // 成長がある方が上位
            }

            // 第2優先: 大幅減少ペナルティ（30%以上減少は下位）
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

            // 第3優先: 低優先度キーワードペナルティ
            if ($hasLowPriorityA !== $hasLowPriorityB) {
                return $hasLowPriorityA <=> $hasLowPriorityB; // 低優先度でない方が上位
            }

            // 第4優先: 小規模ルーム（20人以下）の大幅ペナルティ（高優先度キーワードは除外）
            $smallRoomPenaltyA = ($currentMemberA <= 20 && !$hasHighPriorityA);
            $smallRoomPenaltyB = ($currentMemberB <= 20 && !$hasHighPriorityB);

            if ($smallRoomPenaltyA !== $smallRoomPenaltyB) {
                return $smallRoomPenaltyA <=> $smallRoomPenaltyB; // 小規模でない方が上位
            }

            // 第5優先: メンバー増加数（多い順）
            if ($memberGrowthA !== $memberGrowthB) {
                return $memberGrowthB <=> $memberGrowthA;
            }

            // 第6優先: 現在のメンバー数（多い順）
            if ($currentMemberA !== $currentMemberB) {
                return $currentMemberB <=> $currentMemberA;
            }

            return $a['openchat_id'] <=> $b['openchat_id'];
        });

        // 結果から一時的なフィールドを削除
        foreach ($deletedOpenChats as &$openChat) {
            unset($openChat['member_growth']);
            unset($openChat['peak_decline_rate']);
            unset($openChat['valley_growth_rate']);
            unset($openChat['zero_growth_over_month']);
            unset($openChat['comparison_date']);
        }

        return array_slice($deletedOpenChats, 0, $limit);
    }
}

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
        ['pattern' => '/(?<![0-9０-９])[0-9０-９]{1,2}[\/／][0-9０-９]{1,2](?![0-9０-９])/', 'categories' => null],
    ];

    /**
     * 高優先度キーワード（上位20位以内に押し上げ）
     * display_nameにこれらのキーワードを含むルームを優先表示
     */
    private const HIGH_PRIORITY_KEYWORDS_NAME = [];

    /**
     * 高優先度キーワード（上位20位以内に押し上げ）
     * display_nameまたはdescriptionにこれらのキーワードを含むルームを優先表示
     */
    private const HIGH_PRIORITY_KEYWORDS_NAME_OR_DESC = ['リア友', '友達', '大人', 'シングル'];

    /**
     * 低優先度キーワード（順位を下げる）
     * display_nameにこれらのキーワードを含むルームは下位に配置
     */
    private const LOW_PRIORITY_KEYWORDS = ['也', 'なりきり', 'nrkr', 'オリキャラ', 'ﾅﾘｷﾘ'];

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
            // 最新のメンバー数と比較用のメンバー数を取得（7日前より古い最新のレコード、なければ最古のレコード）
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
                    ) as comparison_date
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
                     LIMIT 1) as oldest ON 1=1";

            $growth = ApiDB::fetch($growthQuery, [
                'openchat_id' => $openChat['openchat_id'],
                'openchat_id2' => $openChat['openchat_id'],
                'openchat_id3' => $openChat['openchat_id'],
                'openchat_id4' => $openChat['openchat_id'],
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

            // 最終結果からcategory_idを削除
            unset($openChat['category_id']);
        }

        // メンバー増加数で並び替え（降順）
        usort($deletedOpenChats, function ($a, $b) {
            $growthA = $a['member_growth'] ?? 0;
            $growthB = $b['member_growth'] ?? 0;

            if ($growthA === $growthB) {
                // 増加数が同じ場合、openchat_idで安定ソート
                return $a['openchat_id'] <=> $b['openchat_id'];
            }

            // 降順で並び替え（増加数が多い順）
            return $growthB <=> $growthA;
        });

        // 特定キーワードとメンバー数による優先度調整
        
        // 優先度別にアイテムを分類  
        $highPriorityItems = [];  // キーワード一致 - 上位20位以内に押し上げ
        $mediumPriorityItems = []; // メンバー50人以上 - 上位48位以内に押し上げ
        $regularItems = [];
        $lowPriorityItems = [];   // 低優先度キーワード - 10位程度下げる
        
        foreach ($deletedOpenChats as $openChat) {
            $hasHighPriorityKeyword = false;
            $hasLowPriorityKeyword = false;
            
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
            
            // 分類
            if ($hasHighPriorityKeyword) {
                $highPriorityItems[] = $openChat;
            } elseif ($hasLowPriorityKeyword) {
                $lowPriorityItems[] = $openChat;
            } elseif ($openChat['current_member_count'] >= 50) {
                $mediumPriorityItems[] = $openChat;
            } else {
                $regularItems[] = $openChat;
            }
        }
        
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

        // 結果から一時的なmember_growthフィールドを削除
        foreach ($deletedOpenChats as &$openChat) {
            unset($openChat['member_growth']);
        }

        return array_slice($deletedOpenChats, 0, $limit);
    }
}

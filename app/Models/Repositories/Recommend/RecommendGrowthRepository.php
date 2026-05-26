<?php

declare(strict_types=1);

namespace App\Models\Repositories\Recommend;

use App\Models\SQLite\SQLiteRankingPosition;
use App\Models\SQLite\SQLiteStatistics;

/**
 * /recommend テーマページの「勢いグラフ」用データ取得(集計のみ・副作用なし)。
 *
 * 2 系統の指標を返す:
 *   1. rank   … LINE 公式「ランキング」(category=0=全体)での掲載部屋の最高(=最小)順位の日次推移【ヒーロー】
 *   2. member … 同一部屋コホートの合計メンバー数(規模の伸び・rank が無いテーマのフォールバック)
 *
 * ヒーローを「最高順位」にした理由:
 *   LINE 公式「ランキング」は人数だけでなく活動量を反映する(人数との Spearman ≒ 0.26。14人の部屋が
 *   全体7位に入ることもある)。活動量込みで安定した指標。ただし TOP-N 掲載"数"はテーマあたり
 *   ~3部屋(0のことも多い)と疎すぎてカウントには向かない。そこで掲載部屋の「最も良い順位
 *   (= MIN(position))」を採る。順位は小さいほど良い=活発。
 *
 * バイアス対策(member):「同一部屋コホート」で集計する。
 *   期間の開始日と終了日の両方に記録がある掲載部屋だけを対象にし、その合計人数を日次で返す。
 *   こうすることで「途中で現れた部屋による段差」「期間中に消えた部屋による下振れ」「記録欠損を
 *   0 として合算する過大評価」を排除し、"今と過去で存在する同じ部屋同士" の比較に揃える。
 *
 * 注意(残るバイアス・呼び出し側/キャプションで明示すること):
 *   - 対象は「掲載中の上位部屋」であり、タグ全体ではない(掲載は人気上位に限られる選抜バイアス)。
 *   - rank は ranking_position.db(ロケール別)、member は statistics.db(ロケール別)から集計。
 *     いずれも ja/tw/th 全ロケール対応。ocgraph_sqlapi は使用しない。
 */
class RecommendGrowthRepository
{
    /** ランキングの全体カテゴリ(=全体ランキング)。 */
    private const RANK_CATEGORY_OVERALL = 0;

    /**
     * テーマの勢いを 1 つの配列にまとめて返す(ja/tw/th 全ロケール対応・呼び出し側はそのままビューへ)。
     *
     * @param int[] $openChatIds 掲載部屋の ID 群
     * @param int $days 遡る日数
     * @return array{
     *   spanDays: int,
     *   rank: array{points: array{date:string, value:int}[], current:int, first:int},
     *   member: array{points: array{date:string, value:int}[], increase:int, rooms:int}
     * }|array{} データ不足時は空配列
     *
     * rank   = 全体ランキングでの掲載部屋の最高(=最小)順位の日次推移(value=その日の MIN(position))【ヒーロー】。
     *          leaderId = 最新日にその最高順位を持っていた部屋の open_chat_id(どの部屋の話か示すため)。
     * member = 同一部屋コホートの合計人数(規模・rank が無いテーマのフォールバック)。
     */
    public static function themeMomentum(array $openChatIds, int $days = 7): array
    {
        $rank   = self::bestRankPositionDaily($openChatIds, $days);
        // rank は ranking_position.db(ロケール別)、member は statistics.db(ロケール別)から集計。
        // いずれも ja/tw/th 全ロケール対応。
        $member = self::themeGrowth($openChatIds, $days);

        // ヒーロー(rank)が 2 点未満 かつ member も空ならグラフ・数値を出す意味が無いので空配列。
        if (count($rank['points']) < 2 && !$member['points']) {
            return [];
        }

        // spanDays は rank(ヒーロー)系列優先・無ければ member 系列。
        $spanSource = $rank['points'] ?: $member['points'];
        $spanDays = 0;
        if (count($spanSource) >= 2) {
            $spanDays = (new \DateTime($spanSource[count($spanSource) - 1]['date']))
                ->diff(new \DateTime($spanSource[0]['date']))->days;
        }

        $rankCurrent = 0;
        $rankFirst = 0;
        if ($rank['points']) {
            $rkp = $rank['points'];
            $rankFirst = $rkp[0]['value'];
            $rankCurrent = $rkp[count($rkp) - 1]['value'];
        }

        $memberIncrease = 0;
        if ($member['points']) {
            $mp = $member['points'];
            $memberIncrease = $mp[count($mp) - 1]['value'] - $mp[0]['value'];
        }

        return [
            'spanDays' => $spanDays,
            'rank' => [
                'points'   => $rank['points'],
                'current'  => $rankCurrent,
                'first'    => $rankFirst,
                'leaderId' => $rank['leaderId'] ?? 0,
            ],
            'member' => [
                'points'   => $member['points'],
                'increase' => $memberIncrease,
                'rooms'    => $member['rooms'],
            ],
        ];
    }

    /**
     * 掲載部屋の「全体ランキングでの最高(=最小)順位」を日次で返す(ヒーロー指標)。
     *
     * 順位は小さいほど良い(=活発)。各日について掲載部屋群の MIN(position) を取り、その日の
     * 「テーマ最高のランキング順位」とする。ranking テーブルの date カラム(実体は 'YYYY-MM-DD'
     * 文字列)で日次グルーピングする。ユニークインデックス (open_chat_id, category, date) があるため
     * where + group by は高速。
     *
     * @param int[] $openChatIds 掲載部屋の ID 群
     * @param int $days 遡る日数
     * @return array{points: array{date:string, value:int}[], leaderId: int}
     *         points: 日付昇順 / value: その日のテーマ最高順位(= MIN(position)・小さいほど良い)
     *         leaderId: 最新日に最高順位だった部屋の open_chat_id(無ければ 0)
     */
    private static function bestRankPositionDaily(array $openChatIds, int $days): array
    {
        $ids = self::sanitizeIds($openChatIds);
        if (!$ids) {
            return ['points' => [], 'leaderId' => 0];
        }

        $in = implode(',', $ids);
        $since = (new \DateTime("-{$days} days"))->format('Y-m-d');
        $category = self::RANK_CATEGORY_OVERALL;

        SQLiteRankingPosition::connect(['mode' => '?mode=ro']);
        $rows = SQLiteRankingPosition::fetchAll(
            "SELECT date, MIN(position) AS value
             FROM ranking
             WHERE open_chat_id IN ($in)
               AND category = $category
               AND date >= '$since'
             GROUP BY date
             ORDER BY date ASC"
        );

        // 「どの部屋の話か」を示すため、最新日に最高(=最小)順位だった部屋を1つ特定する。
        // $latest は DB から取り出した値の再注入になるため、プレースホルダでバインドして埋め込む。
        $leaderId = 0;
        if ($rows) {
            $latest = (string)$rows[count($rows) - 1]['date'];
            $leaderRows = SQLiteRankingPosition::fetchAll(
                "SELECT open_chat_id
                 FROM ranking
                 WHERE open_chat_id IN ($in)
                   AND category = $category
                   AND date = ?
                 ORDER BY position ASC
                 LIMIT 1",
                [$latest]
            );
            $leaderId = $leaderRows ? (int)$leaderRows[0]['open_chat_id'] : 0;
        }

        return [
            'points' => array_map(
                static fn($r) => ['date' => (string)$r['date'], 'value' => (int)$r['value']],
                $rows ?: []
            ),
            'leaderId' => $leaderId,
        ];
    }

    /**
     * 同一部屋コホートの合計メンバー数を日次で返す。
     *
     * @param int[] $openChatIds 掲載部屋の ID 群
     * @param int $days 遡る日数
     * @return array{points: array{date:string, value:int}[], rooms:int}
     *         points: 日付昇順の合計 / rooms: コホートに含まれる部屋数
     */
    public static function themeGrowth(array $openChatIds, int $days = 21): array
    {
        $empty = ['points' => [], 'rooms' => 0];

        $ids = self::sanitizeIds($openChatIds);
        if (count($ids) < 3) {
            return $empty;
        }

        $in = implode(',', $ids);
        // DateTime 由来の Y-m-d 固定書式なのでインライン化(注入リスクなし)。
        $since = (new \DateTime("-{$days} days"))->format('Y-m-d');

        SQLiteStatistics::connect(['mode' => '?mode=ro']);

        $rows = SQLiteStatistics::fetchAll(
            "WITH bounds AS (
                SELECT MIN(date) AS mn, MAX(date) AS mx
                FROM statistics
                WHERE open_chat_id IN ($in) AND date >= '$since'
             ),
             cohort AS (
                SELECT s.open_chat_id
                FROM statistics s, bounds b
                WHERE s.open_chat_id IN ($in)
                  AND s.date IN (b.mn, b.mx)
                GROUP BY s.open_chat_id
                HAVING COUNT(DISTINCT s.date) = 2
             )
             SELECT date,
                    SUM(member) AS value,
                    (SELECT COUNT(*) FROM cohort) AS rooms
             FROM statistics
             WHERE open_chat_id IN (SELECT open_chat_id FROM cohort)
               AND date >= '$since'
             GROUP BY date
             ORDER BY date ASC"
        );

        if (!$rows) {
            return $empty;
        }

        return [
            'points' => array_map(
                static fn($r) => ['date' => (string)$r['date'], 'value' => (int)$r['value']],
                $rows
            ),
            'rooms' => (int)($rows[0]['rooms'] ?? 0),
        ];
    }

    /**
     * 自前の整数 ID 群を int 化して空要素を除去(インライン化の安全担保)。
     *
     * @param int[] $openChatIds
     * @return int[]
     */
    private static function sanitizeIds(array $openChatIds): array
    {
        return array_values(array_filter(array_map('intval', $openChatIds)));
    }
}

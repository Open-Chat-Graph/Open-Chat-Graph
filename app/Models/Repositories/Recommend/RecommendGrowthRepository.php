<?php

declare(strict_types=1);

namespace App\Models\Repositories\Recommend;

use App\Models\SQLite\SQLiteRankingPosition;

/**
 * /recommend テーマページの「勢いグラフ」用データ取得(集計のみ・副作用なし)。
 *
 * 指標は1つ: rank … LINE 公式「ランキング」(category=0=全体)での掲載部屋の最高(=最小)順位の日次推移。
 * ランキング掲載が無い(2点未満の)テーマは「勢い」を出さない(空配列を返す)。旧 member フォールバック
 * (同一部屋コホートの合計メンバー数)は、表示が rank 優先で大半のテーマで計算しても捨てられていたため廃止。
 *
 * ヒーローを「最高順位」にした理由:
 *   LINE 公式「ランキング」は人数だけでなく活動量を反映する(人数との Spearman ≒ 0.26。14人の部屋が
 *   全体7位に入ることもある)。活動量込みで安定した指標。ただし TOP-N 掲載"数"はテーマあたり
 *   ~3部屋(0のことも多い)と疎すぎてカウントには向かない。そこで掲載部屋の「最も良い順位
 *   (= MIN(position))」を採る。順位は小さいほど良い=活発。
 *
 * 注意(残るバイアス・呼び出し側/キャプションで明示すること):
 *   - 対象は「掲載中の上位部屋」であり、タグ全体ではない(掲載は人気上位に限られる選抜バイアス)。
 *   - rank は ranking_position.db(ロケール別)から集計。ja/tw/th 全ロケール対応。ocgraph_sqlapi は使用しない。
 */
class RecommendGrowthRepository implements RecommendGrowthRepositoryInterface
{
    /** ランキングの全体カテゴリ(=全体ランキング)。 */
    private const RANK_CATEGORY_OVERALL = 0;

    /**
     * テーマの勢いを 1 つの配列にまとめて返す(ja/tw/th 全ロケール対応・呼び出し側はそのままビューへ)。
     *
     * 「直近 N 日」の起点は現在時刻ではなく $anchorDate(最後に時報 cron が更新した時刻)を呼び出し側から渡す。
     * 集計窓を実データの存在期間に合わせるためで、クロール遅延やローカルの古いデータでも窓がデータに乗る
     * (本番では現在時刻とほぼ一致するので挙動は変わらない)。時刻取得はこの層の責務にしない。
     *
     * @param int[] $openChatIds 掲載部屋の ID 群
     * @param \DateTime $anchorDate 「直近 N 日」の起点(最終 cron 時刻)
     * @param int $days 遡る日数
     * @return array{
     *   spanDays: int,
     *   rank: array{points: array{date:string, value:int}[], current:int, first:int, leaderId:int}
     * }|array{} 掲載が無い(2点未満)等のデータ不足時は空配列
     *
     * rank = 全体ランキングでの掲載部屋の最高(=最小)順位の日次推移(value=その日の MIN(position))。
     *        leaderId = 最新日にその最高順位を持っていた部屋の open_chat_id(どの部屋の話か示すため)。
     */
    public function themeMomentum(array $openChatIds, \DateTime $anchorDate, int $days = 7): array
    {
        // rank は ranking_position.db(ロケール別・ja/tw/th 全ロケール対応)から集計。
        $rank = $this->bestRankPositionDaily($openChatIds, $anchorDate, $days);

        // ランキング掲載が無い(2点未満の)テーマはグラフを描けないので「勢い」を出さない。
        if (count($rank['points']) < 2) {
            return [];
        }

        $rkp = $rank['points'];
        $spanDays = (int)(new \DateTime($rkp[count($rkp) - 1]['date']))
            ->diff(new \DateTime($rkp[0]['date']))->days;

        return [
            'spanDays' => $spanDays,
            'rank' => [
                'points'   => $rkp,
                'current'  => $rkp[count($rkp) - 1]['value'],
                'first'    => $rkp[0]['value'],
                'leaderId' => $rank['leaderId'] ?? 0,
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
     * @param \DateTime $anchorDate 「直近 N 日」の起点(最終 cron 時刻)
     * @param int $days 遡る日数
     * @return array{points: array{date:string, value:int}[], leaderId: int}
     *         points: 日付昇順 / value: その日のテーマ最高順位(= MIN(position)・小さいほど良い)
     *         leaderId: 最新日に最高順位だった部屋の open_chat_id(無ければ 0)
     */
    private function bestRankPositionDaily(array $openChatIds, \DateTime $anchorDate, int $days): array
    {
        $ids = $this->sanitizeIds($openChatIds);
        if (!$ids) {
            return ['points' => [], 'leaderId' => 0];
        }

        $in = implode(',', $ids);
        $since = (clone $anchorDate)->modify("-{$days} days")->format('Y-m-d');
        $category = self::RANK_CATEGORY_OVERALL;

        SQLiteRankingPosition::connect(SQLiteRankingPosition::WEB_READER);
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
        // 表示順位(rankCurrent)と同じ「最新の有データ日」を使うこと。日付の選び方をどちらか片方だけ
        // 変えると、画面に出る順位とリーダー部屋がズレるため意図的に同一日にそろえている。
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
     * 自前の整数 ID 群を int 化して空要素を除去(インライン化の安全担保)。
     *
     * @param int[] $openChatIds
     * @return int[]
     */
    private function sanitizeIds(array $openChatIds): array
    {
        return array_values(array_filter(array_map('intval', $openChatIds)));
    }
}

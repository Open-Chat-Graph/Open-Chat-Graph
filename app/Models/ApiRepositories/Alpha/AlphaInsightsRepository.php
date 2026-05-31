<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories\Alpha;

use App\Models\Repositories\DB;
use App\Models\SQLite\SQLiteOcgraphSqlapi;

/**
 * α の「高次の考察」用、カテゴリ文脈データ取得リポジトリ。
 *
 * 既存のメンバー数推移 / 順位推移 / 成長ランキング位置は OcNarrativeRepositoryInterface から取得する。
 * このリポジトリは「同カテゴリ内での現在の位置・規模文脈」だけを担当する
 * (open_chat テーブルの現在値スナップショットを集約)。
 *
 * - すべて read-only。書き込みは一切しない
 * - category が NULL のルームは集計対象外 (呼び出し側で category>0 をガード)
 */
class AlphaInsightsRepository
{
    /**
     * 同カテゴリ内での現在のメンバー数ベースの順位・規模文脈を 1 クエリで集約。
     *
     * - rank        : 自分より member が多いルーム数 + 1 (同数は上位扱いしない素朴な順位)
     * - total       : 同カテゴリの総ルーム数
     * - avgMember   : カテゴリ平均メンバー数
     * - sumMember   : カテゴリ総メンバー数 (シェア計算用)
     * - myMember    : 自分の現在メンバー数
     *
     * @return array{
     *     myMember: ?int,
     *     rank: ?int,
     *     total: int,
     *     avgMember: ?float,
     *     sumMember: ?int
     * }
     */
    public function getCategoryContext(int $openChatId, int $category): array
    {
        DB::connect();

        $sql = "
            SELECT
                (SELECT member FROM open_chat WHERE id = :id1) AS my_member,
                (SELECT COUNT(*) FROM open_chat WHERE category = :cat1) AS total,
                (SELECT COUNT(*) FROM open_chat
                    WHERE category = :cat2
                      AND member > (SELECT member FROM open_chat WHERE id = :id2)) AS above,
                (SELECT AVG(member) FROM open_chat WHERE category = :cat3) AS avg_member,
                (SELECT SUM(member) FROM open_chat WHERE category = :cat4) AS sum_member
        ";

        $stmt = DB::$pdo->prepare($sql);
        $stmt->execute([
            'id1' => $openChatId,
            'id2' => $openChatId,
            'cat1' => $category,
            'cat2' => $category,
            'cat3' => $category,
            'cat4' => $category,
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return ['myMember' => null, 'rank' => null, 'total' => 0, 'avgMember' => null, 'sumMember' => null];
        }

        $myMember = $row['my_member'] !== null ? (int)$row['my_member'] : null;
        $total = (int)($row['total'] ?? 0);
        $rank = ($myMember !== null && $total > 0) ? ((int)$row['above'] + 1) : null;

        return [
            'myMember' => $myMember,
            'rank' => $rank,
            'total' => $total,
            'avgMember' => $row['avg_member'] !== null ? (float)$row['avg_member'] : null,
            'sumMember' => $row['sum_member'] !== null ? (int)$row['sum_member'] : null,
        ];
    }

    /**
     * 公式ランキング（全体）の最新総件数。
     *
     * line_official_ranking_total_count に毎時記録される category_id=0（すべて）の
     * activity_ranking_total_count（公式「ランキング」総件数）の最新値を返す。
     * 「{順位}位／{総数}件中」の総数に使う。取れなければ null（呼び出し側は百分位だけ出す等で吸収）。
     *
     * @return ?int
     */
    public function getOfficialRankingTotalCount(): ?int
    {
        $query =
            "SELECT activity_ranking_total_count AS cnt
               FROM line_official_ranking_total_count
              WHERE category_id = 0
              ORDER BY recorded_at DESC
              LIMIT 1";

        try {
            SQLiteOcgraphSqlapi::connect(['mode' => '?mode=ro']);
            $row = SQLiteOcgraphSqlapi::fetch($query);
            SQLiteOcgraphSqlapi::$pdo = null;
        } catch (\Throwable $e) {
            return null;
        }

        if (!$row || !is_array($row) || $row['cnt'] === null) {
            return null;
        }

        $cnt = (int)$row['cnt'];
        return $cnt > 0 ? $cnt : null;
    }

    /**
     * オプチャグラフ独自の成長ランキング（1時間 / 24時間 / 1週間）の総件数。
     * 各テーブルの行数（= その期間に伸びていてランキングに載っている部屋数）。
     * 「全体{順位}位／{総数}件中」の総数に使う。取れなければ各 null。
     *
     * @return array{hour: ?int, day: ?int, week: ?int}
     */
    public function getGrowthRankingTotalCounts(): array
    {
        $query =
            "SELECT
                (SELECT COUNT(*) FROM growth_ranking_past_hour)     AS hour_total,
                (SELECT COUNT(*) FROM growth_ranking_past_24_hours) AS day_total,
                (SELECT COUNT(*) FROM growth_ranking_past_week)     AS week_total";

        try {
            SQLiteOcgraphSqlapi::connect(['mode' => '?mode=ro']);
            $row = SQLiteOcgraphSqlapi::fetch($query);
            SQLiteOcgraphSqlapi::$pdo = null;
        } catch (\Throwable $e) {
            return ['hour' => null, 'day' => null, 'week' => null];
        }

        $norm = static function ($v): ?int {
            if ($v === null) {
                return null;
            }
            $n = (int)$v;
            return $n > 0 ? $n : null;
        };

        return [
            'hour' => $row ? $norm($row['hour_total'] ?? null) : null,
            'day'  => $row ? $norm($row['day_total'] ?? null)  : null,
            'week' => $row ? $norm($row['week_total'] ?? null) : null,
        ];
    }
}

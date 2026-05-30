<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories\Alpha;

use App\Models\Repositories\DB;

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
}

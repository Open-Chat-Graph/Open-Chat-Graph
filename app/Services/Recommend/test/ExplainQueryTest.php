<?php

declare(strict_types=1);

namespace App\Services\Recommend\Test;

use PHPUnit\Framework\TestCase;
use App\Models\Repositories\DB;

/**
 * クエリ実行計画を確認するテスト
 *
 * ## 実行コマンド:
 * ```bash
 * docker compose exec app vendor/bin/phpunit app/Services/Recommend/test/ExplainQueryTest.php
 * ```
 */
class ExplainQueryTest extends TestCase
{
    public function testExplainQueries(): void
    {
        DB::connect();

        $tag = '雑談';
        $minDiffMember = 3;
        $limit = 100;

        echo "\n\n";
        echo "==============================================\n";
        echo "  従来方式のクエリ実行計画\n";
        echo "==============================================\n";

        $legacyQuery = "EXPLAIN SELECT
            oc.id
        FROM
            (
                SELECT
                    t2.id,
                    t1.diff_member AS diff_member
                FROM
                    recommend AS t2
                    JOIN (
                        SELECT
                            *
                        FROM
                            statistics_ranking_hour
                        WHERE
                            diff_member >= :minDiffMember
                    ) AS t1 ON t1.open_chat_id = t2.id
                WHERE
                    t2.tag = :tag
            ) AS ranking
            JOIN open_chat AS oc ON oc.id = ranking.id
        ORDER BY
            ranking.diff_member DESC
        LIMIT
            :limit";

        $legacyExplain = DB::fetchAll($legacyQuery, compact('tag', 'limit', 'minDiffMember'));
        print_r($legacyExplain);

        echo "\n\n";
        echo "==============================================\n";
        echo "  最適化版のクエリ実行計画\n";
        echo "==============================================\n";

        $optimizedQuery = "EXPLAIN SELECT
            oc.id
        FROM
            (
                SELECT
                    t2.id,
                    t1.diff_member AS diff_member
                FROM
                    (SELECT id FROM recommend WHERE tag = :tag) AS t2
                    INNER JOIN statistics_ranking_hour AS t1 ON t1.open_chat_id = t2.id AND t1.diff_member >= :minDiffMember
            ) AS ranking
            JOIN open_chat AS oc ON oc.id = ranking.id
        ORDER BY
            ranking.diff_member DESC
        LIMIT
            :limit";

        $optimizedExplain = DB::fetchAll($optimizedQuery, compact('tag', 'limit', 'minDiffMember'));
        print_r($optimizedExplain);

        echo "\n\n";

        $this->assertTrue(true);
    }
}

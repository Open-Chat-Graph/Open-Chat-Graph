<?php

declare(strict_types=1);

namespace App\Services\Recommend\Test;

use PHPUnit\Framework\TestCase;
use App\Models\Repositories\DB;
use App\Models\RecommendRepositories\RecommendRankingRepository;
use App\Models\RecommendRepositories\OptimizedRecommendRankingRepository;
use App\Services\Recommend\RecommendRankingBuilder;
use App\Services\Recommend\Enum\RecommendListType;

/**
 * RecommendRankingBuilderの新旧実装のパフォーマンス比較テスト
 *
 * ## 実行コマンド:
 * ```bash
 * docker compose exec app vendor/bin/phpunit app/Services/Recommend/test/RecommendRankingBuilderPerformanceTest.php
 * ```
 */
class RecommendRankingBuilderPerformanceTest extends TestCase
{
    /**
     * 新旧実装のパフォーマンス比較と結果の同一性テスト
     */
    public function testPerformanceAndConsistency(): void
    {
        DB::connect();

        // テスト用のタグ
        $testTags = ['雑談', 'ロブロックス'];

        $legacyRepository = new RecommendRankingRepository();
        $optimizedRepository = new OptimizedRecommendRankingRepository();
        $builder = new RecommendRankingBuilder();

        // 最初に旧（従来方式）を実行（キャッシュの影響を受けないように）
        echo "従来方式を実行中...\n";
        $legacyStartTime = microtime(true);
        $legacyResults = [];
        foreach ($testTags as $tag) {
            $legacyResults[$tag] = $builder->getRanking(
                RecommendListType::Tag,
                $tag,
                $tag,
                $legacyRepository
            );
        }
        $legacyTime = microtime(true) - $legacyStartTime;
        echo "完了: " . number_format($legacyTime, 3) . "秒\n\n";

        // クエリキャッシュをクリア
        try {
            DB::$pdo->exec("RESET QUERY CACHE");
        } catch (\Exception) {
            // MySQL 8.0以降はクエリキャッシュがないので無視
        }

        // 次に新（最適化版）を実行
        echo "最適化版を実行中...\n";
        $optimizedStartTime = microtime(true);
        $optimizedResults = [];
        foreach ($testTags as $tag) {
            $optimizedResults[$tag] = $builder->getRanking(
                RecommendListType::Tag,
                $tag,
                $tag,
                $optimizedRepository
            );
        }
        $optimizedTime = microtime(true) - $optimizedStartTime;
        echo "完了: " . number_format($optimizedTime, 3) . "秒\n\n";

        // パフォーマンス比較
        $improvement = (($legacyTime - $optimizedTime) / $legacyTime) * 100;
        $speedupRatio = $legacyTime / $optimizedTime;

        echo "\n";
        echo "==============================================\n";
        echo "  パフォーマンス比較\n";
        echo "==============================================\n";
        echo "テスト対象タグ数: " . count($testTags) . "個\n";
        echo "最適化版: " . number_format($optimizedTime, 3) . "秒\n";
        echo "従来方式: " . number_format($legacyTime, 3) . "秒\n";
        echo "短縮時間: " . number_format($legacyTime - $optimizedTime, 3) . "秒\n";
        echo "改善率: " . number_format($improvement, 1) . "%\n";
        echo "高速化倍率: " . number_format($speedupRatio, 2) . "倍\n";
        echo "\n";
        echo "【200タグでの推定処理時間】\n";
        echo "最適化版: " . number_format(($optimizedTime / count($testTags)) * 200 / 60, 1) . "分\n";
        echo "従来方式: " . number_format(($legacyTime / count($testTags)) * 200 / 60, 1) . "分\n";
        echo "==============================================\n\n";

        // 結果の詳細表示
        echo "==============================================\n";
        echo "  結果の詳細\n";
        echo "==============================================\n";
        foreach ($testTags as $tag) {
            echo "\n【{$tag}】\n";

            $legacyDto = $legacyResults[$tag];
            $optimizedDto = $optimizedResults[$tag];

            echo "従来方式:\n";
            echo "  hour: " . count($legacyDto->hour) . "件\n";
            echo "  day:  " . count($legacyDto->day) . "件\n";
            echo "  week: " . count($legacyDto->week) . "件\n";
            echo "  member: " . count($legacyDto->member) . "件\n";
            echo "  実際の合計: " . count($legacyDto->mergedElements) . "件\n";

            echo "最適化版:\n";
            echo "  hour: " . count($optimizedDto->hour) . "件\n";
            echo "  day:  " . count($optimizedDto->day) . "件\n";
            echo "  week: " . count($optimizedDto->week) . "件\n";
            echo "  member: " . count($optimizedDto->member) . "件\n";
            echo "  実際の合計: " . count($optimizedDto->mergedElements) . "件\n";
        }
        echo "\n";

        // 結果の同一性チェック
        $testTag = $testTags[0];
        $legacyList = $legacyResults[$testTag]->getList(false);
        $optimizedList = $optimizedResults[$testTag]->getList(false);
        $diff = abs(count($legacyList) - count($optimizedList));

        $this->assertLessThan(10, $diff, "新旧の取得件数の差が大きすぎます");
    }
}

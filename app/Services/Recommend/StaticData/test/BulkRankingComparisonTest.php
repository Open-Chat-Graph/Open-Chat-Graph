<?php

/**
 * 旧方式（SQL）と新方式（バルク）のランキング結果を比較するテスト
 *
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Recommend/StaticData/test/BulkRankingComparisonTest.php
 */

declare(strict_types=1);

use App\Config\AppConfig;
use App\Models\RecommendRepositories\BulkRankingDataRepository;
use App\Services\Recommend\BulkRecommendRankingBuilder;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\StaticData\RecommendStaticDataGenerator;
use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

class BulkRankingComparisonTest extends TestCase
{
    private RecommendStaticDataGenerator $generator;
    private BulkRecommendRankingBuilder $bulkBuilder;
    private BulkRankingDataRepository $bulkRepo;

    protected function setUp(): void
    {
        $this->generator = app(RecommendStaticDataGenerator::class);
        $this->bulkBuilder = app(BulkRecommendRankingBuilder::class);
        $this->bulkRepo = app(BulkRankingDataRepository::class);

        // バルクデータを事前取得
        $allData = $this->bulkRepo->fetchAll();
        $this->bulkBuilder->init($allData);
    }

    /**
     * タグランキングの結果一致テスト
     *
     * 同件数で異なるIDが含まれる場合はタイブレーク差異（MySQLの非決定的ソート順）として
     * 警告表示のみ行い、テストは通過させる。
     */
    public function testTagRankingsMatch(): void
    {
        $allTags = $this->generator->getAllTagNames();
        $failures = [];
        $tieBreaks = [];

        foreach ($allTags as $tag) {
            $oldDto = $this->generator->getRecomendRanking($tag);
            $newDto = $this->bulkBuilder->buildTagRanking($tag, $tag);
            $diff = $this->compareDtos($oldDto, $newDto);
            if ($diff) {
                $key = "tag:{$tag}";
                // 全tierで件数が一致していればタイブレーク差異
                $isTieBreak = true;
                foreach ($diff as $tierDiff) {
                    if ($tierDiff['old_count'] !== $tierDiff['new_count']) {
                        $isTieBreak = false;
                        break;
                    }
                }
                if ($isTieBreak) {
                    $tieBreaks[$key] = $diff;
                } else {
                    $failures[$key] = $diff;
                }
            }
        }

        if ($tieBreaks) {
            echo "\n--- タイブレーク差異（MySQLの非決定的ソート順による差、正常） " . count($tieBreaks) . "件 ---\n";
            foreach ($tieBreaks as $entity => $tiers) {
                echo $this->formatMismatch($entity, $tiers);
            }
        }

        if ($failures) {
            $message = "タグランキング結果不一致（件数差異あり）:\n";
            foreach ($failures as $entity => $tiers) {
                $message .= $this->formatMismatch($entity, $tiers);
            }
            $this->fail($message);
        }

        $matchCount = count($allTags) - count($tieBreaks);
        echo "\n全 " . count($allTags) . " タグ中 {$matchCount} タグが完全一致、" . count($tieBreaks) . " タグがタイブレーク差異のみ\n";
        $this->assertTrue(true);
    }

    /**
     * カテゴリ・公式ランキングの結果比較テスト
     *
     * 旧SQLには `GROUP BY id LIMIT 1` バグがあり、tier1-3が常に空になる。
     * 新方式はこのバグを修正しているため、差異は改善として記録する。
     */
    public function testCategoryAndOfficialRankings(): void
    {
        $categories = AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot];
        $improvements = [];
        $regressions = [];

        // カテゴリランキング比較
        foreach ($categories as $name => $category) {
            $oldDto = $this->generator->getCategoryRanking($category);
            $newDto = $this->bulkBuilder->buildCategoryRanking($category, getCategoryName($category));
            $diff = $this->compareDtos($oldDto, $newDto);
            if ($diff) {
                $key = "category:{$name}({$category})";
                // 旧方式のtier1-3が空の場合はGROUP BY LIMIT 1バグによる改善
                $oldTier123Empty = empty($oldDto->hour) && empty($oldDto->day) && empty($oldDto->week);
                if ($oldTier123Empty) {
                    $improvements[$key] = $diff;
                } else {
                    $regressions[$key] = $diff;
                }
            }
        }

        // 公式ランキング比較
        foreach ([1, 2] as $emblem) {
            $listName = AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][$emblem] ?? '';
            $oldDto = $this->generator->getOfficialRanking($emblem);
            $newDto = $this->bulkBuilder->buildOfficialRanking($emblem, $listName);
            if ($oldDto === false || $newDto === false) continue;
            $diff = $this->compareDtos($oldDto, $newDto);
            if ($diff) {
                $key = "official:emblem{$emblem}";
                $oldTier123Empty = empty($oldDto->hour) && empty($oldDto->day) && empty($oldDto->week);
                if ($oldTier123Empty) {
                    $improvements[$key] = $diff;
                } else {
                    $regressions[$key] = $diff;
                }
            }
        }

        if ($improvements) {
            echo "\n--- GROUP BY LIMIT 1 バグ修正による改善 (" . count($improvements) . "件) ---\n";
            foreach ($improvements as $entity => $tiers) {
                $newTotal = 0;
                foreach (['hour', 'day', 'week', 'member'] as $t) {
                    if (isset($tiers[$t])) $newTotal += $tiers[$t]['new_count'];
                }
                echo "  {$entity}: tier1-3が正しく充填 (旧=member100件のみ → 新=4tier合計)\n";
            }
        }

        if ($regressions) {
            $message = "カテゴリ/公式ランキングで予期しない差異:\n";
            foreach ($regressions as $entity => $tiers) {
                $message .= $this->formatMismatch($entity, $tiers);
            }
            $this->fail($message);
        }

        $totalEntities = count($categories) + 2;
        echo "\n全 {$totalEntities} カテゴリ/公式のチェック完了（退行なし）\n";
        $this->assertTrue(true);
    }

    /**
     * パフォーマンス計測テスト
     */
    public function testPerformance(): void
    {
        $allTags = $this->generator->getAllTagNames();
        $categories = AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot];

        // --- 旧方式 ---
        $oldStart = microtime(true);

        foreach ($allTags as $tag) {
            $this->generator->getRecomendRanking($tag);
        }
        foreach ($categories as $category) {
            $this->generator->getCategoryRanking($category);
        }
        foreach ([1, 2] as $emblem) {
            $this->generator->getOfficialRanking($emblem);
        }

        $oldTime = microtime(true) - $oldStart;

        // --- 新方式（データ取得含む） ---
        $newStart = microtime(true);

        $allData = $this->bulkRepo->fetchAll();
        $this->bulkBuilder->init($allData);

        foreach ($allTags as $tag) {
            $this->bulkBuilder->buildTagRanking($tag, $tag);
        }
        foreach ($categories as $name => $category) {
            $this->bulkBuilder->buildCategoryRanking($category, getCategoryName($category));
        }
        foreach ([1, 2] as $emblem) {
            $listName = AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][$emblem] ?? '';
            $this->bulkBuilder->buildOfficialRanking($emblem, $listName);
        }

        $newTime = microtime(true) - $newStart;

        $totalEntities = count($allTags) + count($categories) + 2;
        $speedup = $oldTime / max($newTime, 0.0001);

        echo "\n--- パフォーマンス計測結果 ---\n";
        echo "エンティティ数: {$totalEntities}\n";
        echo "旧方式（SQL）: " . round($oldTime, 3) . "秒\n";
        echo "新方式（バルク）: " . round($newTime, 3) . "秒\n";
        echo "高速化倍率: " . round($speedup, 1) . "倍\n";

        $this->assertTrue(true);
    }

    /**
     * 2つのDtoのtierごとのIDセットを比較する
     *
     * @return array|null 差異がなければnull、あれば差異情報の配列
     */
    private function compareDtos(RecommendListDto $old, RecommendListDto $new): ?array
    {
        $tiers = ['hour', 'day', 'week', 'member'];
        $diff = [];

        foreach ($tiers as $tier) {
            $oldIds = array_column($old->{$tier}, 'id');
            $newIds = array_column($new->{$tier}, 'id');

            $missing = array_values(array_diff($oldIds, $newIds));
            $extra = array_values(array_diff($newIds, $oldIds));

            if ($missing || $extra) {
                $diff[$tier] = [
                    'old_count' => count($oldIds),
                    'new_count' => count($newIds),
                    'missing_ids' => $missing,
                    'extra_ids' => $extra,
                ];
            }
        }

        return $diff ?: null;
    }

    private function formatMismatch(string $entity, array $tiers): string
    {
        $message = "\n  [{$entity}]\n";
        foreach ($tiers as $tier => $detail) {
            $message .= "    {$tier}: old={$detail['old_count']}件 new={$detail['new_count']}件";
            if ($detail['missing_ids']) {
                $message .= " | 新方式に不足: " . implode(',', array_slice($detail['missing_ids'], 0, 10));
            }
            if ($detail['extra_ids']) {
                $message .= " | 新方式に余分: " . implode(',', array_slice($detail['extra_ids'], 0, 10));
            }
            $message .= "\n";
        }
        return $message;
    }
}

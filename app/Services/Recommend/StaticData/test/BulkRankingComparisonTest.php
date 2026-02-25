<?php

/**
 * BulkRecommendRankingBuilder の実行テスト
 *
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Recommend/StaticData/test/BulkRankingComparisonTest.php
 */

declare(strict_types=1);

use App\Config\AppConfig;
use App\Models\RecommendRepositories\BulkRankingDataRepository;
use App\Services\Recommend\BulkRecommendRankingBuilder;
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
     * タグランキングが正常に構築されることを確認
     */
    public function testTagRankings(): void
    {
        $allTags = $this->generator->getAllTagNames();

        foreach ($allTags as $tag) {
            $dto = $this->bulkBuilder->buildTagRanking($tag, $tag);
            $this->assertNotNull($dto);
        }

        echo "\n全 " . count($allTags) . " タグのランキング構築完了\n";
    }

    /**
     * カテゴリ・公式ランキングが正常に構築されることを確認
     */
    public function testCategoryAndOfficialRankings(): void
    {
        $categories = AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot];

        foreach ($categories as $category) {
            $dto = $this->bulkBuilder->buildCategoryRanking($category, getCategoryName($category));
            $this->assertNotNull($dto);
        }

        foreach ([1, 2] as $emblem) {
            $listName = AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][$emblem] ?? '';
            if ($listName) {
                $dto = $this->bulkBuilder->buildOfficialRanking($emblem, $listName);
                $this->assertNotNull($dto);
            }
        }

        echo "\n全 " . (count($categories) + 2) . " カテゴリ/公式のランキング構築完了\n";
    }

    /**
     * パフォーマンス計測テスト
     */
    public function testPerformance(): void
    {
        $allTags = $this->generator->getAllTagNames();
        $categories = AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot];

        $start = microtime(true);

        $allData = $this->bulkRepo->fetchAll();
        $this->bulkBuilder->init($allData);

        foreach ($allTags as $tag) {
            $this->bulkBuilder->buildTagRanking($tag, $tag);
        }
        foreach ($categories as $category) {
            $this->bulkBuilder->buildCategoryRanking($category, getCategoryName($category));
        }
        foreach ([1, 2] as $emblem) {
            $listName = AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][$emblem] ?? '';
            if ($listName) {
                $this->bulkBuilder->buildOfficialRanking($emblem, $listName);
            }
        }

        $elapsed = microtime(true) - $start;
        $totalEntities = count($allTags) + count($categories) + 2;

        echo "\n--- パフォーマンス計測結果 ---\n";
        echo "エンティティ数: {$totalEntities}\n";
        echo "バルク方式: " . round($elapsed, 3) . "秒\n";

        $this->assertTrue(true);
    }
}

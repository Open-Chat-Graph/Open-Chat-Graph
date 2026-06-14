<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Models/RecommendRepositories/test/RecommendRankingRepositoryTest.php
 */

declare(strict_types=1);

use App\Models\RecommendRepositories\RecommendRankingRepository;
use PHPUnit\Framework\TestCase;

class RecommendRankingRepositoryTest extends TestCase
{
    private RecommendRankingRepository $inst;

    protected function setUp(): void
    {
        $this->inst = app(RecommendRankingRepository::class);
    }

    /** 統一ソート(24h増→member→id)が単調になっていること */
    private function assertSorted(array $rows): void
    {
        $prev = null;
        foreach ($rows as $r) {
            $key = [(int)$r['diff_member_24h'], (int)$r['member']];
            if ($prev !== null) {
                $ok = ($prev[0] > $key[0]) || ($prev[0] === $key[0] && $prev[1] >= $key[1]);
                $this->assertTrue($ok, '24h DESC, member DESC で単調であること');
            }
            $prev = $key;
        }
    }

    public function testTag(): void
    {
        $rows = $this->inst->getRankingByTag('オプチャ サポート', 300);
        $this->assertIsArray($rows);
        $this->assertSorted($rows);
    }

    public function testCategory(): void
    {
        $rows = $this->inst->getRankingByCategory(17, 300);
        $this->assertIsArray($rows);
        $this->assertSorted($rows);
    }

    public function testOfficial(): void
    {
        $rows = $this->inst->getRankingByOfficial(2, 300);
        $this->assertIsArray($rows);
        $this->assertSorted($rows);
    }

    /**
     * バルク取得(getRankingByTagsBulk)が、タグごとの getRankingByTag を1クエリにまとめても
     * 各タグの結果(件数・並び順・id列)が単発と一致すること。毎時バッチの N+1 解消の安全網。
     */
    public function testBulkMatchesPerTag(): void
    {
        $tags = ['オプチャ サポート', '雑談', 'ゲーム', '英語', '存在しないはずのタグ_zzz'];
        $bulk = $this->inst->getRankingByTagsBulk($tags, 300);
        $this->assertIsArray($bulk);

        foreach ($tags as $tag) {
            $single = $this->inst->getRankingByTag($tag, 300);
            $bulkRows = $bulk[$tag] ?? [];

            $this->assertSame(
                array_map(fn($r) => (int)$r['id'], $single),
                array_map(fn($r) => (int)$r['id'], $bulkRows),
                "タグ[{$tag}]の id 列が単発とバルクで一致すること"
            );

            foreach ($bulkRows as $r) {
                $this->assertArrayHasKey('table_name', $r);
                $this->assertArrayHasKey('diff_member_24h', $r);
                $this->assertArrayNotHasKey('_bulk_tag', $r);
                $this->assertArrayNotHasKey('_rn', $r);
            }
        }
    }
}

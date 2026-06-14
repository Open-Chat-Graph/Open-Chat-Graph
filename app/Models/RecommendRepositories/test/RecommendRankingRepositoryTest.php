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
}

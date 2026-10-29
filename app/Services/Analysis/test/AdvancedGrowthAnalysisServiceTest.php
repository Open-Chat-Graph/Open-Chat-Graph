<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Analysis/test/AdvancedGrowthAnalysisServiceTest.php
 */

declare(strict_types=1);

use App\Models\Repositories\Analysis\AnalysisRoomRepositoryInterface;
use App\Models\Repositories\Analysis\AnalysisStatsRepositoryInterface;
use App\Services\Analysis\AdvancedGrowthAnalysisService;
use App\Services\Storage\FileStorageInterface;
use PHPUnit\Framework\TestCase;

class AdvancedGrowthAnalysisServiceTest extends TestCase
{
    private const MAX_ID = 300;

    private string $dir;
    private AdvancedGrowthAnalysisService $service;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ocg_analysis_test_' . getmypid() . '_' . uniqid();
        mkdir($this->dir, 0755, true);
        file_put_contents($this->dir . '/hourly.txt', '2026-10-29 11:30:00');

        $dir = $this->dir;
        $storage = new class($dir) implements FileStorageInterface {
            public function __construct(private string $dir) {}
            public function getStorageFilePath(string $k): string
            {
                return $k === 'analysisJobsDir' ? $this->dir . '/jobs' : $this->dir . '/hourly.txt';
            }
            private function r(string $p): string
            {
                return str_starts_with($p, '@') ? $this->getStorageFilePath(substr($p, 1)) : $p;
            }
            public function safeFileRewrite(string $f, string $c): void
            {
                $f = $this->r($f);
                $t = tempnam(dirname($f), 'tmp_');
                file_put_contents($t, $c);
                rename($t, $f);
            }
            public function saveSerializedFile(string $f, mixed $d): void
            {
                $this->safeFileRewrite($f, gzencode(serialize($d)));
            }
            public function putContents(string $f, string $c): void
            {
                file_put_contents($this->r($f), $c);
            }
            public function getContents(string $f): string
            {
                return file_get_contents($this->r($f));
            }
            public function getSerializedFile(string $f): mixed
            {
                $f = $this->r($f);
                return file_exists($f) ? unserialize(gzdecode(file_get_contents($f))) : false;
            }
        };

        $rooms = new class(self::MAX_ID) implements AnalysisRoomRepositoryInterface {
            public function __construct(private int $max) {}
            public function getMaxOpenChatId(): int
            {
                return $this->max;
            }
            public function getRoomsInRange(int $lo, int $hi): array
            {
                $o = [];
                for ($i = max($lo, 1); $i < min($hi, $this->max + 1); $i++) {
                    $o[$i] = ['category' => $i % 3, 'member' => 100 + $i];
                }
                return $o;
            }
            public function findIdsByKeyword(string $k): array
            {
                return [11, 21, 31];
            }
            public function hydrate(array $ids): array
            {
                $o = [];
                foreach ($ids as $id) {
                    $o[$id] = ['name' => "n{$id}", 'desc' => '', 'member' => 100 + $id, 'img' => '', 'emblem' => 0, 'joinMethodType' => 0, 'category' => $id % 3];
                }
                return $o;
            }
        };

        // base = id の約数で粗く（同値を作る）。増加数 = cur - base。id が大きいほど増加が大きい
        $stats = new class implements AnalysisStatsRepositoryInterface {
            public function getMemberAsOf(int $lo, int $hi, string $date): array
            {
                $o = [];
                for ($i = max($lo, 1); $i < $hi; $i++) {
                    if ($i % 10 !== 0) {
                        $o[$i] = $i % 2 === 0 ? 40 : 60;
                    }
                }
                return $o;
            }
            public function getSteadyAggregates(int $lo, int $hi, string $f, string $t): array
            {
                return [];
            }
        };

        $this->service = new AdvancedGrowthAnalysisService($stats, $rooms, $storage);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/jobs/*') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->dir . '/jobs');
        @unlink($this->dir . '/hourly.txt');
        @rmdir($this->dir);
    }

    private function runToDone(): array
    {
        for ($i = 0; $i < 40; $i++) {
            $st = $this->service->advance('increase', 'year', null, null);
            if ($st['done']) {
                return $st;
            }
            $this->assertLessThan(100, $st['percent']);
        }
        $this->fail('status が完了しない');
    }

    public function testAdvanceFinishesAfterChunkCountPollsAndKeepsOnlyFinishedFile(): void
    {
        $polls = 0;
        do {
            $st = $this->service->advance('increase', 'year', null, null);
            $polls++;
        } while (!$st['done'] && $polls < 40);

        $this->assertSame(30, $polls);
        $this->assertSame(270, $st['computed'], '1〜300 のうち 10 の倍数(30件)を除く');
        $this->assertSame([], glob($this->dir . '/jobs/*.c[0-9][0-9]') ?: [], 'チャンクは結合後に消える');
        $this->assertCount(1, glob($this->dir . '/jobs/*.bin') ?: []);
    }

    public function testDefaultOrderIsIncreaseDescendingAndPagesDoNotOverlap(): void
    {
        $this->runToDone();
        $p0 = $this->service->result('increase', 'year', null, null, '', 'desc', 0, '', 0, 100);
        $p1 = $this->service->result('increase', 'year', null, null, '', 'desc', 0, '', 1, 100);

        $this->assertSame(270, $p0[0]['totalCount']);
        $this->assertCount(100, $p0);
        $diffs = array_column($p0, 'diff');
        $sorted = $diffs;
        rsort($sorted);
        $this->assertSame($sorted, $diffs);
        $this->assertSame([], array_intersect(array_column($p0, 'id'), array_column($p1, 'id')));
        $this->assertArrayNotHasKey('totalCount', $p1[0]);
    }

    public function testCategoryKeywordAscAndRateFilters(): void
    {
        $this->runToDone();

        $cat = $this->service->result('increase', 'year', null, null, '', 'desc', 1, '', 0, 1000);
        $this->assertNotEmpty($cat);
        $this->assertSame([1], array_values(array_unique(array_column($cat, 'category'))));

        $kw = $this->service->result('increase', 'year', null, null, '', 'desc', 0, 'x', 0, 1000);
        $this->assertEqualsCanonicalizing([11, 21, 31], array_column($kw, 'id'));

        $asc = array_column($this->service->result('increase', 'year', null, null, 'count', 'asc', 0, '', 0, 1000), 'diff');
        $sorted = $asc;
        sort($sorted);
        $this->assertSame($sorted, $asc);

        // 増加率ソートは base>=50 のみ（base=40 の偶数 id は除外される）
        $rate = $this->service->result('increase', 'year', null, null, 'rate', 'desc', 0, '', 0, 1000);
        $this->assertNotEmpty($rate);
        foreach ($rate as $r) {
            $this->assertGreaterThanOrEqual(50, $r['base']);
        }
    }

    public function testUnfinishedJobReturnsEmptyResult(): void
    {
        $this->assertSame([['totalCount' => 0]], $this->service->result('increase', 'year', null, null, '', 'desc', 0, '', 0, 100));
        $this->assertSame([], $this->service->result('increase', 'year', null, null, '', 'desc', 0, '', 1, 100));
    }

    public function testMissingChunkRestartsInsteadOfFailing(): void
    {
        for ($i = 0; $i < 29; $i++) {
            $this->service->advance('increase', 'year', null, null);
        }
        foreach (glob($this->dir . '/jobs/*.c00') as $f) {
            unlink($f);
        }
        $st = $this->service->advance('increase', 'year', null, null);
        $this->assertFalse($st['done']);
        $this->assertSame(0, $st['percent']);
        $this->assertSame(270, $this->runToDone()['computed']);
    }
}

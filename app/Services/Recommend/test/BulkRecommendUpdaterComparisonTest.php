<?php

/**
 * 旧方式（SQL）と新方式（バルクPHP）のRecommendUpdater結果を比較するテスト
 *
 * 一時DBを作成して本番の1/3データで比較。本番DBへの書き込みなし。
 *
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Recommend/test/BulkRecommendUpdaterComparisonTest.php
 */

declare(strict_types=1);

use App\Config\AppConfig;
use App\Models\Repositories\DB;
use App\Services\Recommend\BulkRecommendUpdater;
use App\Services\Recommend\RecommendUpdater;
use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

class BulkRecommendUpdaterComparisonTest extends TestCase
{
    private string $originalDbName;
    private string $testDbName;

    protected function setUp(): void
    {
        DB::connect();

        $this->originalDbName = AppConfig::$dbName[MimimalCmsConfig::$urlRoot];
        $this->testDbName = $this->originalDbName . '_test_bulk_' . getmypid();

        $this->createTestDatabase();
    }

    protected function tearDown(): void
    {
        debug("テストDB削除中: {$this->testDbName}");

        try {
            DB::$pdo->exec("DROP DATABASE IF EXISTS `{$this->testDbName}`");
        } catch (\Throwable $e) {
            debug("WARNING: テストDB削除失敗: {$e->getMessage()}");
        }

        DB::$pdo = null;
        DB::connect();

        debug("元のDBに復帰完了");
    }

    /**
     * 一時DBを作成し、本番の1/3データをコピー
     */
    private function createTestDatabase(): void
    {
        $orig = $this->originalDbName;
        $test = $this->testDbName;

        debug("テストDB作成中: {$test}");

        DB::$pdo->exec("CREATE DATABASE `{$test}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $tables = ['open_chat', 'recommend', 'oc_tag', 'oc_tag2', 'modify_recommend'];
        foreach ($tables as $table) {
            DB::$pdo->exec("CREATE TABLE `{$test}`.`{$table}` LIKE `{$orig}`.`{$table}`");
        }

        debug("スキーマコピー完了、データコピー中...");

        DB::$pdo->exec(
            "INSERT INTO `{$test}`.`open_chat`
             SELECT * FROM `{$orig}`.`open_chat` WHERE MOD(id, 3) = 0"
        );

        DB::$pdo->exec(
            "INSERT INTO `{$test}`.`modify_recommend`
             SELECT mr.* FROM `{$orig}`.`modify_recommend` mr
             INNER JOIN `{$test}`.`open_chat` oc ON mr.id = oc.id"
        );

        // テストDBに切り替え
        DB::$pdo = null;
        DB::connect(['dbName' => $test]);

        $count = DB::$pdo->query("SELECT COUNT(*) FROM open_chat")->fetchColumn();
        debug("テストDB作成完了: {$count}行");
    }

    /**
     * 旧方式と新方式の結果を比較するテスト
     */
    public function testBulkUpdaterMatchesOriginal(): void
    {
        // --- 旧方式実行 ---
        debug("旧方式（SQL）実行中...");
        $oldStart = microtime(true);

        $oldUpdater = app(RecommendUpdater::class);
        $oldUpdater->updateRecommendTables(false);

        $oldTime = microtime(true) - $oldStart;
        debug("旧方式完了: " . round($oldTime, 3) . "秒");

        // 旧方式の結果を保存
        $oldRecommend = $this->fetchTagMap('recommend');
        $oldOcTag = $this->fetchTagMap('oc_tag');
        $oldOcTag2 = $this->fetchTagMap('oc_tag2');

        debug("旧方式結果: recommend=" . count($oldRecommend)
            . " oc_tag=" . count($oldOcTag)
            . " oc_tag2=" . count($oldOcTag2));

        // --- テーブルクリア ---
        debug("テーブルクリア中...");
        DB::$pdo->exec("TRUNCATE TABLE recommend");
        DB::$pdo->exec("TRUNCATE TABLE oc_tag");
        DB::$pdo->exec("TRUNCATE TABLE oc_tag2");

        // --- 新方式実行 ---
        debug("新方式（バルクPHP）実行中...");
        $newStart = microtime(true);

        $newUpdater = app(BulkRecommendUpdater::class);
        $newUpdater->updateRecommendTables(false);

        $newTime = microtime(true) - $newStart;
        debug("新方式完了: " . round($newTime, 3) . "秒");

        // 新方式の結果を保存
        $newRecommend = $this->fetchTagMap('recommend');
        $newOcTag = $this->fetchTagMap('oc_tag');
        $newOcTag2 = $this->fetchTagMap('oc_tag2');

        debug("新方式結果: recommend=" . count($newRecommend)
            . " oc_tag=" . count($newOcTag)
            . " oc_tag2=" . count($newOcTag2));

        // --- 比較 ---
        debug("--- 比較結果 ---");

        $recommendDiff = $this->compareMaps('recommend', $oldRecommend, $newRecommend);
        $ocTagDiff = $this->compareMaps('oc_tag', $oldOcTag, $newOcTag);
        $ocTag2Diff = $this->compareMaps('oc_tag2', $oldOcTag2, $newOcTag2);

        // パフォーマンスサマリー
        $speedup = $oldTime / max($newTime, 0.0001);
        debug("--- パフォーマンス ---");
        debug("旧方式: " . round($oldTime, 3) . "秒");
        debug("新方式: " . round($newTime, 3) . "秒");
        debug("高速化倍率: " . round($speedup, 1) . "倍");

        // 差異率1%未満をアサート
        $totalOld = max(count($oldRecommend) + count($oldOcTag) + count($oldOcTag2), 1);
        $totalDiff = $recommendDiff + $ocTagDiff + $ocTag2Diff;
        $diffRate = $totalDiff / $totalOld;

        debug("総差異率: " . round($diffRate * 100, 2) . "% ({$totalDiff}/{$totalOld})");

        $this->assertLessThan(
            0.01,
            $diffRate,
            "差異率が1%以上あります: " . round($diffRate * 100, 2) . "%"
        );
    }

    /**
     * テーブルから id => tag のマップを取得
     *
     * @return array<int, string>
     */
    private function fetchTagMap(string $table): array
    {
        $stmt = DB::$pdo->query("SELECT id, tag FROM {$table} ORDER BY id");
        $map = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $map[(int)$row['id']] = $row['tag'];
        }
        return $map;
    }

    /**
     * 2つのタグマップを比較し、差異数を返す
     *
     * @param array<int, string> $old
     * @param array<int, string> $new
     * @return int 差異数
     */
    private function compareMaps(string $tableName, array $old, array $new): int
    {
        $allIds = array_unique(array_merge(array_keys($old), array_keys($new)));
        $missingInNew = 0;
        $extraInNew = 0;
        $tagDiff = 0;
        $tagDiffSamples = [];

        foreach ($allIds as $id) {
            $oldTag = $old[$id] ?? null;
            $newTag = $new[$id] ?? null;

            if ($oldTag !== null && $newTag === null) {
                $missingInNew++;
            } elseif ($oldTag === null && $newTag !== null) {
                $extraInNew++;
            } elseif ($oldTag !== $newTag) {
                $tagDiff++;
                if (count($tagDiffSamples) < 5) {
                    $tagDiffSamples[] = "id={$id}: old=\"{$oldTag}\" → new=\"{$newTag}\"";
                }
            }
        }

        $total = $missingInNew + $extraInNew + $tagDiff;

        if ($total === 0) {
            debug("[{$tableName}] old=" . count($old) . " new=" . count($new) . " → 完全一致");
        } else {
            $msg = "[{$tableName}] old=" . count($old) . " new=" . count($new)
                . " → 差異{$total}件 (不足:{$missingInNew} 余分:{$extraInNew} タグ違い:{$tagDiff})";
            foreach ($tagDiffSamples as $sample) {
                $msg .= "\n  {$sample}";
            }
            debug($msg);
        }

        return $total;
    }
}

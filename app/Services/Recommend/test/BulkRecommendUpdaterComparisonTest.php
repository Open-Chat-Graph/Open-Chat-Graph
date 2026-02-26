<?php

/**
 * 旧方式（SQL）と新方式（バルクPHP）のRecommendUpdater結果を比較するテスト
 *
 * 2つの一時DBを作成して本番の1/10データで独立実行・比較。本番DBへの書き込みなし。
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
    private string $oldDbName;
    private string $newDbName;

    protected function setUp(): void
    {
        DB::connect();

        $this->originalDbName = AppConfig::$dbName[MimimalCmsConfig::$urlRoot];
        $pid = getmypid();
        $this->oldDbName = $this->originalDbName . '_test_old_' . $pid;
        $this->newDbName = $this->originalDbName . '_test_new_' . $pid;

        $this->createTestDatabases();
    }

    protected function tearDown(): void
    {
        // 本番DBに接続し直してからDROP
        DB::$pdo = null;
        DB::connect();

        foreach ([$this->oldDbName, $this->newDbName] as $dbName) {
            try {
                DB::$pdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
                debug("テストDB削除完了: {$dbName}");
            } catch (\Throwable $e) {
                debug("WARNING: テストDB削除失敗 ({$dbName}): {$e->getMessage()}");
            }
        }

        debug("元のDBに復帰完了");
    }

    /**
     * 2つの一時DBを作成し、本番の1/10データを同一内容でコピー
     */
    private function createTestDatabases(): void
    {
        $orig = $this->originalDbName;
        $tables = ['open_chat', 'recommend', 'oc_tag', 'oc_tag2', 'modify_recommend'];

        // ランダムなMOD値で1/100を抽出（毎回異なるサンプル）
        $modValue = random_int(0, 99);

        // --- DB1（旧方式用）を作成 ---
        debug("旧方式用テストDB作成中: {$this->oldDbName}");
        DB::$pdo->exec("CREATE DATABASE `{$this->oldDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        foreach ($tables as $table) {
            DB::$pdo->exec("CREATE TABLE `{$this->oldDbName}`.`{$table}` LIKE `{$orig}`.`{$table}`");
        }
        DB::$pdo->exec(
            "INSERT INTO `{$this->oldDbName}`.`open_chat`
             SELECT * FROM `{$orig}`.`open_chat` WHERE MOD(id, 100) = {$modValue}"
        );
        DB::$pdo->exec(
            "INSERT INTO `{$this->oldDbName}`.`modify_recommend`
             SELECT mr.* FROM `{$orig}`.`modify_recommend` mr
             INNER JOIN `{$this->oldDbName}`.`open_chat` oc ON mr.id = oc.id"
        );

        // --- DB2（新方式用）を作成 ---
        debug("新方式用テストDB作成中: {$this->newDbName}");
        DB::$pdo->exec("CREATE DATABASE `{$this->newDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        foreach ($tables as $table) {
            DB::$pdo->exec("CREATE TABLE `{$this->newDbName}`.`{$table}` LIKE `{$orig}`.`{$table}`");
        }
        // DB1と同一データをコピー
        DB::$pdo->exec(
            "INSERT INTO `{$this->newDbName}`.`open_chat`
             SELECT * FROM `{$this->oldDbName}`.`open_chat`"
        );
        DB::$pdo->exec(
            "INSERT INTO `{$this->newDbName}`.`modify_recommend`
             SELECT * FROM `{$this->oldDbName}`.`modify_recommend`"
        );

        // データ件数を確認
        $count = DB::$pdo->query("SELECT COUNT(*) FROM `{$this->oldDbName}`.`open_chat`")->fetchColumn();
        debug("テストDB作成完了: open_chat {$count}行（MOD値={$modValue}で1/100抽出）");
    }

    /**
     * 指定DBに接続を切り替える
     */
    private function switchDb(string $dbName): void
    {
        DB::$pdo = null;
        DB::connect(['dbName' => $dbName]);
    }

    /**
     * 旧方式と新方式の結果・速度・メモリ使用量を比較するテスト
     */
    public function testBulkUpdaterMatchesOriginal(): void
    {
        // === 旧方式（SQL）実行 ===
        $this->switchDb($this->oldDbName);

        debug("旧方式（SQL）実行中...");
        $oldMemBefore = memory_get_usage();
        $oldStart = microtime(true);

        $oldUpdater = app(RecommendUpdater::class);
        $oldUpdater->updateRecommendTables(false);

        $oldTime = microtime(true) - $oldStart;
        $oldMemAfter = memory_get_usage();
        $oldPeakMem = memory_get_peak_usage();
        $oldMemDelta = $oldMemAfter - $oldMemBefore;
        debug("旧方式完了: " . round($oldTime, 3) . "秒");

        // 旧方式の結果を保存
        $oldRecommend = $this->fetchTagMap('recommend');
        $oldOcTag = $this->fetchTagMap('oc_tag');
        $oldOcTag2 = $this->fetchTagMap('oc_tag2');

        debug("旧方式結果: recommend=" . count($oldRecommend)
            . " oc_tag=" . count($oldOcTag)
            . " oc_tag2=" . count($oldOcTag2));

        // 旧方式のピークメモリを記録（新方式測定前のベースライン）
        $peakBeforeNew = memory_get_peak_usage();

        // === 新方式（バルクPHP）実行 ===
        $this->switchDb($this->newDbName);

        debug("新方式（バルクPHP）実行中...");
        $newMemBefore = memory_get_usage();
        $newStart = microtime(true);

        $newUpdater = app(BulkRecommendUpdater::class);
        $newUpdater->updateRecommendTables(false);

        $newTime = microtime(true) - $newStart;
        $newMemAfter = memory_get_usage();
        $newPeakMem = memory_get_peak_usage();
        $newMemDelta = $newMemAfter - $newMemBefore;
        debug("新方式完了: " . round($newTime, 3) . "秒");

        // 新方式の結果を保存
        $newRecommend = $this->fetchTagMap('recommend');
        $newOcTag = $this->fetchTagMap('oc_tag');
        $newOcTag2 = $this->fetchTagMap('oc_tag2');

        debug("新方式結果: recommend=" . count($newRecommend)
            . " oc_tag=" . count($newOcTag)
            . " oc_tag2=" . count($newOcTag2));

        // === 結果比較 ===
        debug("=== 結果比較 ===");

        $recommendDiff = $this->compareMaps('recommend', $oldRecommend, $newRecommend);
        $ocTagDiff = $this->compareMaps('oc_tag', $oldOcTag, $newOcTag);
        $ocTag2Diff = $this->compareMaps('oc_tag2', $oldOcTag2, $newOcTag2);

        // === パフォーマンスサマリー ===
        $speedup = $oldTime / max($newTime, 0.0001);
        debug("=== パフォーマンス ===");
        debug("旧方式: " . round($oldTime, 3) . "秒");
        debug("新方式: " . round($newTime, 3) . "秒");
        debug("高速化倍率: " . round($speedup, 1) . "倍");

        // === メモリ使用量サマリー ===
        debug("=== メモリ使用量 ===");
        debug("旧方式: 実行中増分 " . $this->formatBytes($oldMemDelta)
            . " / ピーク " . $this->formatBytes($oldPeakMem));
        debug("新方式: 実行中増分 " . $this->formatBytes($newMemDelta)
            . " / ピーク " . $this->formatBytes($newPeakMem));
        if ($peakBeforeNew < $newPeakMem) {
            debug("新方式によるピーク増加: " . $this->formatBytes($newPeakMem - $peakBeforeNew));
        }

        // === アサーション ===
        $totalOld = max(count($oldRecommend) + count($oldOcTag) + count($oldOcTag2), 1);
        $totalDiff = $recommendDiff + $ocTagDiff + $ocTag2Diff;
        $diffRate = $totalDiff / $totalOld;

        debug("=== 総合結果 ===");
        debug("総差異率: " . round($diffRate * 100, 2) . "% ({$totalDiff}/{$totalOld})");

        $this->assertSame(
            0,
            $totalDiff,
            "旧方式と新方式の結果に差異があります: {$totalDiff}件 "
            . "(recommend:{$recommendDiff} oc_tag:{$ocTagDiff} oc_tag2:{$ocTag2Diff})"
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
                if (count($tagDiffSamples) < 5) {
                    $tagDiffSamples[] = "id={$id}: old=\"{$oldTag}\" → new=NULL（旧にあり新になし）";
                }
            } elseif ($oldTag === null && $newTag !== null) {
                $extraInNew++;
                if (count($tagDiffSamples) < 5) {
                    $tagDiffSamples[] = "id={$id}: old=NULL → new=\"{$newTag}\"（旧になく新にあり）";
                }
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

    /**
     * バイト数を人間が読みやすい形式に変換
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes < 0) {
            return '-' . $this->formatBytes(-$bytes);
        }
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}

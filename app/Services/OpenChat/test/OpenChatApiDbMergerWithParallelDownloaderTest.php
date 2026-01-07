<?php

/**
 * OpenChatApiDbMergerWithParallelDownloader のテスト
 *
 * テスト実行コマンド（Docker内で実行）:
 * docker-compose exec app vendor/bin/phpunit app/Services/OpenChat/test/OpenChatApiDbMergerWithParallelDownloaderTest.php
 *
 * 注意: このテストは実際にAPIダウンロードを実行します
 */

use App\Config\AppConfig;
use App\Config\OpenChatCrawlerConfig;
use App\Services\OpenChat\OpenChatApiDbMergerWithParallelDownloader;
use App\Services\OpenChat\Updater\Process\OpenChatApiDbMergerProcess;
use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

class OpenChatApiDbMergerWithParallelDownloaderTest extends TestCase
{
    /**
     * 並列ダウンロード処理の統合テスト
     *
     * - バッチサイズ3で全カテゴリをダウンロード
     * - OpenChatApiDbMergerProcessをモック化してDB変更を防ぐ
     * - ダウンロードされたファイルの存在と内容を確認
     * - 各バッチの処理時間、レコード数、カテゴリ組み合わせを表示
     */
    public function testParallelDownloadWithMock()
    {
        MimimalCmsConfig::$urlRoot = '';

        // OpenChatApiDbMergerProcessのモックを作成してDB変更を防ぐ
        $processMock = $this->createMock(OpenChatApiDbMergerProcess::class);
        $processMock->method('validateAndMapToOpenChatDtoCallback')->willReturn(null);

        $merger = new OpenChatApiDbMergerWithParallelDownloader(
            app(\App\Models\Repositories\ParallelDownloadOpenChatStateRepositoryInterface::class),
            app(\App\Services\RankingPosition\Store\RankingPositionStore::class),
            app(\App\Services\RankingPosition\Store\RisingPositionStore::class),
            $processMock,
            app(\App\Models\Repositories\SyncOpenChatStateRepositoryInterface::class)
        );

        OpenChatApiDbMergerWithParallelDownloader::setKillFlagFalse();

        // 処理時間計測開始
        $startTime = microtime(true);

        // バッチサイズ3で全カテゴリのダウンロードを実行
        $batchSize = 1;
        $merger->fetchOpenChatApiRankingAll($batchSize);

        // 総処理時間を計算（秒単位）
        $totalTime = microtime(true) - $startTime;

        // === ダウンロード結果の分析と表示 ===

        // カテゴリ配列の準備（順方向と逆方向）
        $categoryArray = array_values(OpenChatCrawlerConfig::PARALLEL_DOWNLOADER_CATEGORY_ORDER['/tw']);
        $categoryReverse = array_reverse($categoryArray);

        // バッチごとにカテゴリキーを分割（例: [[0,1,2], [3,4,5], ...]）
        $batchKeys = array_chunk(array_keys($categoryArray), $batchSize);

        // カテゴリIDから日本語名へのマッピング（例: 20 => '流行／美妝'）
        $categoryNames = array_flip(AppConfig::OPEN_CHAT_CATEGORY['/tw']);
        $totalRecords = 0;

        // 結果の表示開始
        echo "\n\n=== ダウンロード結果 ===\n";
        echo "バッチサイズ: {$batchSize}\n";
        echo "総処理時間: " . round($totalTime / 60, 2) . " 分\n\n";

        // バッチごとに結果を集計して表示
        foreach ($batchKeys as $batchIndex => $batch) {
            $batchRecords = 0;  // このバッチの合計レコード数
            $categories = [];   // このバッチのカテゴリ組み合わせ情報

            // バッチ内の各カテゴリペアを処理
            foreach ($batch as $key) {
                // === Ranking側の処理 ===
                // 順方向配列からカテゴリIDを取得
                $rankingCategory = $categoryArray[$key];
                $rankingFile = AppConfig::getStorageFilePath('openChatRankingPositionDir') . "/{$rankingCategory}.json";

                // ファイルが存在し、空でないことを確認
                $this->assertFileExists($rankingFile, "Rankingファイル (カテゴリ: {$rankingCategory}) が存在しません");
                $this->assertGreaterThan(0, filesize($rankingFile), "Rankingファイル (カテゴリ: {$rankingCategory}) が空です");

                // JSONファイルからデータを読み込み、レコード数を集計
                $rankingData = json_decode(file_get_contents($rankingFile), true);
                $rankingCount = count($rankingData);
                $batchRecords += $rankingCount;

                // === Rising側の処理 ===
                // 逆方向配列から対応するカテゴリIDを取得（負荷分散のため）
                $risingCategory = $categoryReverse[$key];
                $risingFile = AppConfig::getStorageFilePath('openChatRisingPositionDir') . "/{$risingCategory}.json";

                // ファイルが存在し、空でないことを確認
                $this->assertFileExists($risingFile, "Risingファイル (カテゴリ: {$risingCategory}) が存在しません");
                $this->assertGreaterThan(0, filesize($risingFile), "Risingファイル (カテゴリ: {$risingCategory}) が空です");

                // JSONファイルからデータを読み込み、レコード数を集計
                $risingData = json_decode(file_get_contents($risingFile), true);
                $risingCount = count($risingData);
                $batchRecords += $risingCount;

                // カテゴリ組み合わせの表示用文字列を作成
                $categories[] = sprintf(
                    "  [Ranking: %s (%d件)] + [Rising: %s (%d件)]",
                    $categoryNames[$rankingCategory] ?? $rankingCategory,
                    $rankingCount,
                    $categoryNames[$risingCategory] ?? $risingCategory,
                    $risingCount
                );
            }

            // 全体の合計レコード数に加算
            $totalRecords += $batchRecords;

            // バッチごとの平均処理時間を推定（総時間 ÷ バッチ数）
            $batchTime = round($totalTime / count($batchKeys), 2);

            // バッチの結果を表示
            echo "バッチ " . ($batchIndex + 1) . ":\n";
            echo "  推定処理時間: " . round($batchTime / 60, 2) . " 分\n";
            echo "  レコード数: {$batchRecords} 件\n";
            echo "  カテゴリ組み合わせ:\n";
            foreach ($categories as $cat) {
                echo "    {$cat}\n";
            }
            echo "\n";
        }

        // 全体の合計を表示
        echo "=== 合計 ===\n";
        echo "総レコード数: {$totalRecords} 件\n";
        echo "総処理時間: " . round($totalTime / 60, 2) . " 分\n\n";

        $this->assertTrue(true);
    }
}

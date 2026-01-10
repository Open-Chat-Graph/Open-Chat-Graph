<?php

use PHPUnit\Framework\TestCase;
use App\Services\Cron\OcreviewApiDataImporter;
use Shadow\DB;

class OcreviewApiDataImporterTest extends TestCase
{
    public function testExecute()
    {
        // Set a long execution time limit for the test
        set_time_limit(3600 * 1);

        // Create an instance of OcreviewApiDataImporter
        $importer = app(OcreviewApiDataImporter::class);

        // Execute the import process
        $importer->execute();

        // Assert that the import process completed successfully
        $this->assertTrue(true);
    }

    /**
     * line_internal_idが重複する場合のUNIQUE制約違反を検証
     */
    public function testLineInternalIdUniqueConstraint()
    {
        // Set a long execution time limit for the test
        set_time_limit(3600 * 1);

        // Create an instance of OcreviewApiDataImporter
        $importer = app(OcreviewApiDataImporter::class);

        // MySQLに同じline_internal_id（emid）で異なるIDを持つレコードを作成
        DB::connect();

        // テスト用のemid
        $testEmid = 'test_emid_' . time();

        // 最初のレコード（id=999999）
        $stmt = DB::$pdo->prepare(
            "INSERT INTO open_chat (id, name, emid, member, description, img_url)
             VALUES (999999, 'Test Chat 1', ?, 100, 'Test', 'test.jpg')
             ON DUPLICATE KEY UPDATE emid = VALUES(emid)"
        );
        $stmt->execute([$testEmid]);

        // データインポートを実行
        $importer->execute();

        // MySQLの999999のレコードを削除し、同じemidで新しいID（999998）を作成
        DB::$pdo->exec("DELETE FROM open_chat WHERE id = 999999");

        $stmt = DB::$pdo->prepare(
            "INSERT INTO open_chat (id, name, emid, member, description, img_url)
             VALUES (999998, 'Test Chat 2', ?, 200, 'Test 2', 'test2.jpg')
             ON DUPLICATE KEY UPDATE emid = VALUES(emid)"
        );
        $stmt->execute([$testEmid]);

        // 再度データインポートを実行（UNIQUE制約違反が発生しないことを確認）
        try {
            $importer->execute();
            $this->assertTrue(true);
        } catch (\PDOException $e) {
            // UNIQUE制約違反が発生した場合はテスト失敗
            $this->fail('UNIQUE constraint violation occurred: ' . $e->getMessage());
        } finally {
            // テストデータをクリーンアップ
            DB::$pdo->exec("DELETE FROM open_chat WHERE id IN (999999, 999998)");
        }
    }
}

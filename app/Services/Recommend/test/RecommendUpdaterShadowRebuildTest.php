<?php

declare(strict_types=1);

use App\Models\RecommendRepositories\RecommendTagRepository;
use App\Models\Repositories\DB;
use App\Services\Recommend\RecommendUpdater;
use App\Services\Recommend\TagDefinition\JsonRecommendUpdaterTags;
use App\Services\Storage\FileStorageInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

// docker compose exec -T app vendor/bin/phpunit app/Services/Recommend/test/RecommendUpdaterShadowRebuildTest.php
//
// シャドウテーブル方式フル再構築（rebuildAllViaShadowSwap）の振る舞いを検証する。
// テストは専用DB ocgraph_recommend_test を作って捨てる方式のみ（本番テーブルには一切触れない）。
class RecommendUpdaterShadowRebuildTest extends TestCase
{
    private const TEST_DB_NAME = 'ocgraph_recommend_test';

    private RecommendUpdater $recommendUpdater;
    private FileStorageInterface&Stub $mockFileStorage;

    protected function setUp(): void
    {
        // MimimalCmsConfig::$urlRoot を '' に設定（日本語版のテスト）
        MimimalCmsConfig::$urlRoot = '';

        // DB接続してテスト専用DBを作成・切り替え
        DB::connect();
        DB::$pdo->exec('CREATE DATABASE IF NOT EXISTS `' . self::TEST_DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        DB::$pdo->exec('USE `' . self::TEST_DB_NAME . '`');

        // テストテーブルを作成
        $this->createTestTables();

        // テストデータを投入
        $this->insertTestData();

        // FileStorageInterface のモックを作成（既存 RecommendUpdaterTest を踏襲）
        $this->mockFileStorage = $this->createStub(FileStorageInterface::class);

        $this->mockFileStorage
            ->method('getStorageFilePath')
            ->willReturn(__FILE__);

        $this->mockFileStorage
            ->method('getContents')
            ->willReturnCallback(function ($filepath) {
                if ($filepath === '@tagUpdatedAtDatetime') {
                    // 過去日時を返し、差分回収（updateRecommendTables(true)）で全ルームが対象になるようにする
                    return '2020-01-01 00:00:00';
                }
                return '';
            });

        $this->mockFileStorage
            ->method('safeFileRewrite');

        $this->recommendUpdater = new RecommendUpdater(
            $this->mockFileStorage,
            new RecommendTagRepository(),
            new JsonRecommendUpdaterTags()
        );
    }

    protected function tearDown(): void
    {
        // テスト専用DBを丸ごと削除（本番DBには一切触れない）
        DB::$pdo->exec('DROP DATABASE IF EXISTS `' . self::TEST_DB_NAME . '`');

        // 接続を破棄して、後続テストの DB::connect() が本番DBへ再接続できるようにする。
        // （DROP した DB を USE したままにすると "No database selected" を後続に波及させるため）
        DB::$pdo = null;
    }

    private function createTestTables(): void
    {
        DB::execute("
            CREATE TABLE `open_chat` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
                `img_url` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                `local_img_url` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '',
                `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
                `member` int(11) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `emid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                `category` int(11) DEFAULT NULL,
                `api_created_at` int(11) DEFAULT NULL,
                `emblem` int(11) DEFAULT NULL,
                `join_method_type` int(11) NOT NULL DEFAULT 0,
                `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
                `update_items` text DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `emid` (`emid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        DB::execute("
            CREATE TABLE `oc_tag` (
                `id` int(11) NOT NULL,
                `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                PRIMARY KEY (`id`),
                KEY `tag` (`tag`(768))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        DB::execute("
            CREATE TABLE `oc_tag2` (
                `id` int(11) NOT NULL,
                `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                PRIMARY KEY (`id`),
                KEY `tag` (`tag`(768))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        DB::execute("
            CREATE TABLE `recommend` (
                `id` int(11) NOT NULL,
                `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                PRIMARY KEY (`id`),
                KEY `tag` (`tag`(768))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        DB::execute("
            CREATE TABLE `modify_recommend` (
                `id` int(11) NOT NULL,
                `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                `time` datetime NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function insertTestData(): void
    {
        foreach ($this->generateTestRooms() as $room) {
            DB::execute(
                "INSERT INTO open_chat (name, img_url, description, member, emid, category, updated_at)
                 VALUES (:name, :img_url, :description, :member, :emid, :category, :updated_at)",
                [
                    'name' => $room['name'],
                    'img_url' => $room['img_url'],
                    'description' => $room['description'],
                    'member' => $room['member'],
                    'emid' => $room['emid'],
                    'category' => $room['category'],
                    'updated_at' => $room['updated_at'],
                ]
            );
        }
    }

    /**
     * テスト用ルームデータ。タグが付くもの・付かないものを混在させ、
     * 各種マッチ経路（strongest / nameStrong / category系）を踏ませる。
     */
    private function generateTestRooms(): array
    {
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $rooms = [];

        $rooms[] = ['name' => 'スタバ無料配布情報', 'description' => 'スターバックスの無料ドリンク配布情報', 'img_url' => 'https://example.com/s1.jpg', 'member' => 1000, 'emid' => 'sb_001', 'category' => 12, 'updated_at' => $now];
        $rooms[] = ['name' => 'スタバギフト交換会', 'description' => 'スタバのギフトを交換', 'img_url' => 'https://example.com/s2.jpg', 'member' => 500, 'emid' => 'sb_002', 'category' => 12, 'updated_at' => $now];
        $rooms[] = ['name' => '生成AI情報交換', 'description' => 'ChatGPTやClaudeなどのLLMについて', 'img_url' => 'https://example.com/ai.jpg', 'member' => 2000, 'emid' => 'ai_001', 'category' => 17, 'updated_at' => $now];
        $rooms[] = ['name' => 'ポケモンカード情報', 'description' => 'ポケカの最新情報', 'img_url' => 'https://example.com/pk.jpg', 'member' => 3000, 'emid' => 'pk_001', 'category' => 17, 'updated_at' => $now];
        $rooms[] = ['name' => 'プロセカ攻略情報', 'description' => 'プロジェクトセカイの攻略', 'img_url' => 'https://example.com/ps.jpg', 'member' => 1800, 'emid' => 'ps_001', 'category' => 17, 'updated_at' => $now];
        $rooms[] = ['name' => '原神攻略', 'description' => '原神の攻略情報交換', 'img_url' => 'https://example.com/gs.jpg', 'member' => 2200, 'emid' => 'gs_001', 'category' => 17, 'updated_at' => $now];
        $rooms[] = ['name' => '大学生雑談', 'description' => '大学生が集まる雑談部屋', 'img_url' => 'https://example.com/u.jpg', 'member' => 5000, 'emid' => 'u_001', 'category' => 7, 'updated_at' => $now];
        $rooms[] = ['name' => '25卒交流会', 'description' => '25卒の学生が集まる', 'img_url' => 'https://example.com/j.jpg', 'member' => 3500, 'emid' => 'j_001', 'category' => 7, 'updated_at' => $now];
        $rooms[] = ['name' => '呪術廻戦ファン', 'description' => '呪術廻戦について語ろう', 'img_url' => 'https://example.com/jj.jpg', 'member' => 4000, 'emid' => 'jj_001', 'category' => 22, 'updated_at' => $now];
        $rooms[] = ['name' => 'にじさんじファン', 'description' => 'にじさんじライバーについて', 'img_url' => 'https://example.com/nj.jpg', 'member' => 3400, 'emid' => 'nj_001', 'category' => 26, 'updated_at' => $now];
        $rooms[] = ['name' => 'MBTI診断結果共有', 'description' => 'INFPやENFJなどのMBTI結果', 'img_url' => 'https://example.com/mb.jpg', 'member' => 1700, 'emid' => 'mb_001', 'category' => 8, 'updated_at' => $now];
        $rooms[] = ['name' => '高校生限定雑談', 'description' => '高校生だけの部屋', 'img_url' => 'https://example.com/hs.jpg', 'member' => 4200, 'emid' => 'hs_001', 'category' => 7, 'updated_at' => $now];

        // タグが付かない一般ルームも混ぜる
        for ($i = 1; $i <= 20; $i++) {
            $rooms[] = [
                'name' => "一般雑談部屋 #{$i}",
                'description' => "みんなで楽しく雑談しましょう",
                'img_url' => "https://example.com/c{$i}.jpg",
                'member' => 100 + $i,
                'emid' => "general_{$i}",
                'category' => 8,
                'updated_at' => $now,
            ];
        }

        return $rooms;
    }

    /**
     * 指定テーブルの全行を id => tag の連想配列で取得（id昇順）。
     */
    private function fetchTagMap(string $table): array
    {
        $rows = DB::$pdo->query("SELECT id, tag FROM `{$table}` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = $row['tag'];
        }
        return $map;
    }

    /**
     * 現在のテストDBに存在するテーブル名一覧。
     */
    private function fetchTableNames(): array
    {
        return DB::$pdo
            ->query("SELECT table_name FROM information_schema.tables WHERE table_schema = '" . self::TEST_DB_NAME . "'")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * 1. 通常の全件更新（updateRecommendTables(false)）と、クリーンな状態からの
     *    シャドウ再構築（rebuildAllViaShadowSwap）が同一結果になることを検証。
     *    fresh状態なら seed コピーは空なので両者は等価になるはず。
     */
    public function testShadowRebuildMatchesFullUpdate()
    {
        // (a) 通常フル更新
        $this->recommendUpdater->updateRecommendTables(false);
        $expectedRecommend = $this->fetchTagMap('recommend');
        $expectedOcTag = $this->fetchTagMap('oc_tag');
        $expectedOcTag2 = $this->fetchTagMap('oc_tag2');

        // 一度クリーンに戻す（live を空にして fresh 状態を再現）
        DB::execute("DELETE FROM recommend");
        DB::execute("DELETE FROM oc_tag");
        DB::execute("DELETE FROM oc_tag2");

        // (b) シャドウ再構築
        $this->recommendUpdater->rebuildAllViaShadowSwap();
        $actualRecommend = $this->fetchTagMap('recommend');
        $actualOcTag = $this->fetchTagMap('oc_tag');
        $actualOcTag2 = $this->fetchTagMap('oc_tag2');

        // 何らかのタグが実際に付いていること（テストが空同士の比較で素通りしないため）
        $this->assertNotEmpty($expectedRecommend, 'recommend にタグが1件も付いていません（テストデータの想定が崩れています）');

        $this->assertSame($expectedRecommend, $actualRecommend, 'recommend がフル更新とシャドウ再構築で一致しません');
        $this->assertSame($expectedOcTag, $actualOcTag, 'oc_tag がフル更新とシャドウ再構築で一致しません');
        $this->assertSame($expectedOcTag2, $actualOcTag2, 'oc_tag2 がフル更新とシャドウ再構築で一致しません');
    }

    /**
     * 2. シャドウ再構築を2回実行しても結果が変わらない（冪等）ことを検証。
     */
    public function testShadowRebuildIsIdempotent()
    {
        $this->recommendUpdater->rebuildAllViaShadowSwap();
        $firstRecommend = $this->fetchTagMap('recommend');
        $firstOcTag = $this->fetchTagMap('oc_tag');
        $firstOcTag2 = $this->fetchTagMap('oc_tag2');

        $this->recommendUpdater->rebuildAllViaShadowSwap();
        $secondRecommend = $this->fetchTagMap('recommend');
        $secondOcTag = $this->fetchTagMap('oc_tag');
        $secondOcTag2 = $this->fetchTagMap('oc_tag2');

        $this->assertNotEmpty($firstRecommend, 'recommend にタグが1件も付いていません（テストデータの想定が崩れています）');

        $this->assertSame($firstRecommend, $secondRecommend, 'recommend が2回目の再構築で変化しました（冪等でない）');
        $this->assertSame($firstOcTag, $secondOcTag, 'oc_tag が2回目の再構築で変化しました（冪等でない）');
        $this->assertSame($firstOcTag2, $secondOcTag2, 'oc_tag2 が2回目の再構築で変化しました（冪等でない）');
    }

    /**
     * 3. open_chat に存在しない id（削除済みルームの tombstone タグ）の recommend 行が
     *    再構築後も同じタグで残存することを検証（削除ページ/SEOが依存する暗黙仕様の退行防止）。
     */
    public function testDeletedRoomTagSurvivesRebuild()
    {
        // まず通常状態を作る
        $this->recommendUpdater->updateRecommendTables(false);

        // open_chat に存在しない id の tombstone を recommend へ手動INSERT
        $ghostId = 999999;
        $ghostTag = '削除済みルームのタグ';
        DB::execute(
            "INSERT INTO recommend (id, tag) VALUES (:id, :tag)",
            ['id' => $ghostId, 'tag' => $ghostTag]
        );

        // open_chat には存在しないことを確認（前提）
        $exists = DB::$pdo->query("SELECT COUNT(*) FROM open_chat WHERE id = {$ghostId}")->fetchColumn();
        $this->assertSame(0, (int)$exists, '前提が崩れています: ghost id が open_chat に存在しています');

        // 再構築
        $this->recommendUpdater->rebuildAllViaShadowSwap();

        // tombstone 行が同じタグで残存していること
        $stmt = DB::$pdo->prepare("SELECT tag FROM recommend WHERE id = ?");
        $stmt->execute([$ghostId]);
        $tag = $stmt->fetchColumn();

        $this->assertNotFalse($tag, '削除済みルームの tombstone タグが再構築で消えました（SEO/削除ページ退行）');
        $this->assertSame($ghostTag, $tag, '削除済みルームの tombstone タグが再構築で書き換わりました');
    }

    /**
     * 4. modify_recommend の管理者上書きタグが、再構築後も recommend 上で保持されることを検証。
     */
    public function testModifyRecommendOverridePreservedAfterRebuild()
    {
        // 通常状態を作る
        $this->recommendUpdater->updateRecommendTables(false);

        // 既にタグが付いているルームを1つ選ぶ（modify は「マッチ済みID」を上書きする仕様）
        $target = DB::$pdo->query("SELECT id FROM recommend ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($target, 'recommend にタグ付きルームがありません');
        $targetId = (int)$target['id'];

        $overrideTag = '管理者上書きタグ';

        // modify_recommend に上書きを登録し、recommend にも同じ上書きを反映（管理操作後の状態を模す）
        DB::execute("INSERT INTO modify_recommend (id, tag) VALUES (:id, :tag)", ['id' => $targetId, 'tag' => $overrideTag]);
        DB::execute(
            "INSERT INTO recommend (id, tag) VALUES (:id, :tag) ON DUPLICATE KEY UPDATE tag = VALUES(tag)",
            ['id' => $targetId, 'tag' => $overrideTag]
        );

        // 再構築
        $this->recommendUpdater->rebuildAllViaShadowSwap();

        // recommend の当該 id が上書きタグのままであること
        $stmt = DB::$pdo->prepare("SELECT tag FROM recommend WHERE id = ?");
        $stmt->execute([$targetId]);
        $tag = $stmt->fetchColumn();

        $this->assertSame($overrideTag, $tag, '管理者上書きタグが再構築で失われました（reapplyAllModifyRecommend が効いていない）');
    }

    /**
     * 5. 再構築後に shadow 用の残骸テーブル（*_build / *_old）が一切残っていないことを検証。
     */
    public function testNoLeftoverShadowTables()
    {
        $this->recommendUpdater->rebuildAllViaShadowSwap();

        $tables = $this->fetchTableNames();

        $leftovers = [
            'recommend_build', 'recommend_old',
            'oc_tag_build', 'oc_tag_old',
            'oc_tag2_build', 'oc_tag2_old',
        ];

        foreach ($leftovers as $name) {
            $this->assertNotContains(
                $name,
                $tables,
                "シャドウ用の残骸テーブル {$name} が再構築後も残っています"
            );
        }

        // live テーブルは存在すること
        $this->assertContains('recommend', $tables);
        $this->assertContains('oc_tag', $tables);
        $this->assertContains('oc_tag2', $tables);
    }

    /**
     * 6. シャドウ再構築は base(ja) 専用。tw/th では LogicException で拒否されること
     *    （tw/th の recommend系テーブルはユニークキーが無く upsert が重複行を生むため）。
     */
    public function testShadowRebuildRejectsNonBaseLocale()
    {
        MimimalCmsConfig::$urlRoot = '/tw';
        try {
            $this->recommendUpdater->rebuildAllViaShadowSwap();
            $this->fail('tw/th では LogicException が投げられるべきです');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('ja', $e->getMessage());
        } finally {
            MimimalCmsConfig::$urlRoot = '';
        }
    }
}

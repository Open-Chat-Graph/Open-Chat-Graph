<?php

declare(strict_types=1);

use App\Models\Repositories\DB;
use App\Services\Recommend\RecommendUpdater;
use App\Services\Recommend\TagDefinition\Ja\RecommendUpdaterTags;
use App\Services\Storage\FileStorageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

// docker compose exec app vendor/bin/phpunit app/Services/Recommend/test/RecommendUpdaterTest.php
class RecommendUpdaterTest extends TestCase
{
    private RecommendUpdater $recommendUpdater;
    private FileStorageInterface|MockObject $mockFileStorage;

    protected function setUp(): void
    {
        // MimimalCmsConfig::$urlRoot を '' に設定（日本語版のテスト）
        MimimalCmsConfig::$urlRoot = '';

        // DB接続
        DB::connect();

        // テストテーブルを作成
        $this->createTestTables();

        // テストデータを投入
        $this->insertTestData();

        // FileStorageInterface のモックを作成
        $this->mockFileStorage = $this->createMock(FileStorageInterface::class);

        // getContents のモック設定（複数のファイルパスに対応）
        $this->mockFileStorage
            ->method('getContents')
            ->willReturnCallback(function ($filepath) {
                if ($filepath === '@tagUpdatedAtDatetime') {
                    return '2020-01-01 00:00:00';
                } elseif ($filepath === '@openChatSubCategoriesTag') {
                    // テストに必要なカテゴリ情報を返す
                    return json_encode([
                        '5' => [], // 生活
                        '7' => [], // 学校
                        '8' => [], // 趣味
                        '12' => [], // グルメ
                        '17' => [], // ゲーム・アプリ
                        '22' => [], // マンガ・アニメ
                        '26' => [], // 芸能人・有名人
                        '33' => [], // 音楽
                        '40' => [], // 金融・ビジネス
                        '41' => [], // クリエイター
                    ]);
                }
                return '';
            });

        $this->mockFileStorage
            ->expects($this->any())
            ->method('safeFileRewrite');

        // RecommendUpdater インスタンスを作成
        $this->recommendUpdater = new RecommendUpdater(
            $this->mockFileStorage,
            new RecommendUpdaterTags()
        );
    }

    protected function tearDown(): void
    {
        // テストテーブルを削除（テンポラリテーブルも含む）
        DB::execute('DROP TEMPORARY TABLE IF EXISTS recommend_temp');
        DB::execute('DROP TEMPORARY TABLE IF EXISTS oc_tag_temp');
        DB::execute('DROP TEMPORARY TABLE IF EXISTS oc_tag2_temp');
        DB::execute('DROP TABLE IF EXISTS open_chat');
        DB::execute('DROP TABLE IF EXISTS oc_tag');
        DB::execute('DROP TABLE IF EXISTS oc_tag2');
        DB::execute('DROP TABLE IF EXISTS recommend');
        DB::execute('DROP TABLE IF EXISTS modify_recommend');
    }

    private function createTestTables(): void
    {
        // open_chat テーブル
        DB::execute("
            CREATE TABLE IF NOT EXISTS `open_chat` (
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

        // oc_tag テーブル
        DB::execute("
            CREATE TABLE IF NOT EXISTS `oc_tag` (
                `id` int(11) NOT NULL,
                `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                PRIMARY KEY (`id`),
                KEY `tag` (`tag`(768))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // oc_tag2 テーブル
        DB::execute("
            CREATE TABLE IF NOT EXISTS `oc_tag2` (
                `id` int(11) NOT NULL,
                `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                PRIMARY KEY (`id`),
                KEY `tag` (`tag`(768))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // recommend テーブル
        DB::execute("
            CREATE TABLE IF NOT EXISTS `recommend` (
                `id` int(11) NOT NULL,
                `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                PRIMARY KEY (`id`),
                KEY `tag` (`tag`(768))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // modify_recommend テーブル
        DB::execute("
            CREATE TABLE IF NOT EXISTS `modify_recommend` (
                `id` int(11) NOT NULL,
                `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                `time` datetime NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function insertTestData(): void
    {
        $testRooms = $this->generateTestRooms();

        foreach ($testRooms as $room) {
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
                    'updated_at' => $room['updated_at']
                ]
            );
        }
    }

    /**
     * テスト用のルームデータを生成（100件程度）
     */
    private function generateTestRooms(): array
    {
        $rooms = [];
        $now = (new DateTime())->format('Y-m-d H:i:s');

        // 1. スタバ関連のルーム（カテゴリ12: グルメ）
        $rooms[] = [
            'name' => 'スタバ無料配布情報',
            'description' => 'スターバックスの無料ドリンク配布情報をシェアするグループです',
            'img_url' => 'https://example.com/starbucks.jpg',
            'member' => 1000,
            'emid' => 'starbucks_free_001',
            'category' => 12,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => 'スタバギフト交換会',
            'description' => 'スタバのギフトを交換しましょう',
            'img_url' => 'https://example.com/starbucks2.jpg',
            'member' => 500,
            'emid' => 'starbucks_gift_002',
            'category' => 12,
            'updated_at' => $now
        ];

        // 2. ChatGPT / AI関連のルーム（カテゴリ17: ゲーム・アプリ）
        $rooms[] = [
            'name' => '生成AI情報交換',
            'description' => 'ChatGPTやClaudeなどのLLMについて語り合いましょう',
            'img_url' => 'https://example.com/ai.jpg',
            'member' => 2000,
            'emid' => 'chatgpt_001',
            'category' => 17,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => 'AIイラスト共有',
            'description' => 'AI絵画やAIイラストを共有するグループです',
            'img_url' => 'https://example.com/ai_art.jpg',
            'member' => 1500,
            'emid' => 'ai_art_001',
            'category' => 41,
            'updated_at' => $now
        ];

        // 3. ポケモン関連のルーム（カテゴリ17: ゲーム・アプリ）
        $rooms[] = [
            'name' => 'ポケモンカード情報',
            'description' => 'ポケカの最新情報を共有',
            'img_url' => 'https://example.com/pokemon.jpg',
            'member' => 3000,
            'emid' => 'pokemon_card_001',
            'category' => 17,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => 'ポケモン大好きチャット',
            'description' => 'ポケモン好きが集まるグループ',
            'img_url' => 'https://example.com/pokemon2.jpg',
            'member' => 2500,
            'emid' => 'pokemon_fans_001',
            'category' => 17,
            'updated_at' => $now
        ];

        // 4. プロセカ関連のルーム（カテゴリ17: ゲーム・アプリ）
        $rooms[] = [
            'name' => 'プロセカ攻略情報',
            'description' => 'プロジェクトセカイの攻略情報を共有',
            'img_url' => 'https://example.com/prosekai.jpg',
            'member' => 1800,
            'emid' => 'prosekai_001',
            'category' => 17,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => 'プロセカなりきり雑談',
            'description' => 'プロセカのキャラでなりきり雑談しよう',
            'img_url' => 'https://example.com/prosekai_nrkr.jpg',
            'member' => 1200,
            'emid' => 'prosekai_nrkr_001',
            'category' => 17,
            'updated_at' => $now
        ];

        // 5. 原神関連のルーム（カテゴリ17: ゲーム・アプリ）
        $rooms[] = [
            'name' => '原神攻略',
            'description' => '原神の攻略情報交換',
            'img_url' => 'https://example.com/genshin.jpg',
            'member' => 2200,
            'emid' => 'genshin_001',
            'category' => 17,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => '原神なりきり',
            'description' => '原神のキャラでなりきりロールプレイ',
            'img_url' => 'https://example.com/genshin_nrkr.jpg',
            'member' => 900,
            'emid' => 'genshin_nrkr_001',
            'category' => 17,
            'updated_at' => $now
        ];

        // 6. 大学生・就活関連のルーム（カテゴリ7: 学校）
        $rooms[] = [
            'name' => '大学生雑談',
            'description' => '大学生が集まる雑談部屋',
            'img_url' => 'https://example.com/univ.jpg',
            'member' => 5000,
            'emid' => 'university_001',
            'category' => 7,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => '25卒交流会',
            'description' => '25卒の学生が集まるグループ',
            'img_url' => 'https://example.com/job_hunt.jpg',
            'member' => 3500,
            'emid' => 'job_hunt_25_001',
            'category' => 7,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => '春から○○大学',
            'description' => '新入生同士の情報交換グループ',
            'img_url' => 'https://example.com/freshman.jpg',
            'member' => 800,
            'emid' => 'freshman_001',
            'category' => 7,
            'updated_at' => $now
        ];

        // 7. アニメ・マンガ関連（カテゴリ22: マンガ・アニメ）
        $rooms[] = [
            'name' => '呪術廻戦ファン',
            'description' => '呪術廻戦について語ろう',
            'img_url' => 'https://example.com/jujutsu.jpg',
            'member' => 4000,
            'emid' => 'jujutsu_001',
            'category' => 22,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => '呪術廻戦なりきり',
            'description' => '呪術廻戦のキャラでなりきり',
            'img_url' => 'https://example.com/jujutsu_nrkr.jpg',
            'member' => 1100,
            'emid' => 'jujutsu_nrkr_001',
            'category' => 22,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => 'ワンピース考察',
            'description' => 'ONEPIECEの考察グループ',
            'img_url' => 'https://example.com/onepiece.jpg',
            'member' => 3200,
            'emid' => 'onepiece_001',
            'category' => 22,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => 'ワンピースカード販売情報',
            'description' => 'ONEPIECEカードゲームの販売情報',
            'img_url' => 'https://example.com/onepiece_card.jpg',
            'member' => 2800,
            'emid' => 'onepiece_card_001',
            'category' => 22,
            'updated_at' => $now
        ];

        // 8. K-POP・アイドル関連（カテゴリ26: 芸能人・有名人）
        $rooms[] = [
            'name' => 'SEVENTEEN CARAT',
            'description' => 'セブチファンの交流部屋',
            'img_url' => 'https://example.com/seventeen.jpg',
            'member' => 2600,
            'emid' => 'seventeen_001',
            'category' => 26,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => '日プ女子情報',
            'description' => 'PRODUCE 101 JAPAN THE GIRLSの情報交換',
            'img_url' => 'https://example.com/produce.jpg',
            'member' => 1900,
            'emid' => 'produce_girls_001',
            'category' => 26,
            'updated_at' => $now
        ];

        // 9. MBTI関連（カテゴリ8: 趣味）
        $rooms[] = [
            'name' => 'MBTI診断結果共有',
            'description' => 'INFPやENFJなどのMBTI結果を共有しよう',
            'img_url' => 'https://example.com/mbti.jpg',
            'member' => 1700,
            'emid' => 'mbti_001',
            'category' => 8,
            'updated_at' => $now
        ];

        // 10. LGBT関連（カテゴリ8: 趣味）
        $rooms[] = [
            'name' => 'LGBT交流会',
            'description' => 'セクマイやトランスジェンダーの方々の交流部屋',
            'img_url' => 'https://example.com/lgbt.jpg',
            'member' => 1300,
            'emid' => 'lgbt_001',
            'category' => 8,
            'updated_at' => $now
        ];

        // 11. メンタルヘルス関連（カテゴリ5: 生活）
        $rooms[] = [
            'name' => '不登校の子を持つ親の会',
            'description' => '不登校の悩みを共有しましょう',
            'img_url' => 'https://example.com/futoukou.jpg',
            'member' => 600,
            'emid' => 'futoukou_001',
            'category' => 5,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => 'ADHD当事者会',
            'description' => '発達障害やADHDの当事者グループ',
            'img_url' => 'https://example.com/adhd.jpg',
            'member' => 850,
            'emid' => 'adhd_001',
            'category' => 5,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => 'うつ病サポート',
            'description' => '鬱病の方々が支え合うグループ',
            'img_url' => 'https://example.com/utsubyou.jpg',
            'member' => 720,
            'emid' => 'utsubyou_001',
            'category' => 5,
            'updated_at' => $now
        ];

        // 12. ポイ活関連（カテゴリ5: 生活）
        $rooms[] = [
            'name' => 'ポイ活情報交換',
            'description' => 'ポイカツの最新情報をシェア',
            'img_url' => 'https://example.com/point.jpg',
            'member' => 2400,
            'emid' => 'point_001',
            'category' => 5,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => 'メルカリ出品テクニック',
            'description' => 'メルカリで上手に売るコツ',
            'img_url' => 'https://example.com/mercari.jpg',
            'member' => 1600,
            'emid' => 'mercari_001',
            'category' => 5,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => 'TikTok Lite魚交換',
            'description' => 'TikTokライトのはちみつや魚を交換',
            'img_url' => 'https://example.com/tiktok.jpg',
            'member' => 3100,
            'emid' => 'tiktok_lite_001',
            'category' => 5,
            'updated_at' => $now
        ];

        // 13. 年代別雑談（カテゴリ7: 学校、カテゴリ8: 趣味）
        $rooms[] = [
            'name' => '30代雑談',
            'description' => '30代の雑談グループ',
            'img_url' => 'https://example.com/30s.jpg',
            'member' => 2100,
            'emid' => 'age_30s_001',
            'category' => 8,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => '40代の集い',
            'description' => '40代が集まる部屋',
            'img_url' => 'https://example.com/40s.jpg',
            'member' => 1800,
            'emid' => 'age_40s_001',
            'category' => 8,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => '50代コミュニティ',
            'description' => '50代のコミュニティ',
            'img_url' => 'https://example.com/50s.jpg',
            'member' => 1400,
            'emid' => 'age_50s_001',
            'category' => 8,
            'updated_at' => $now
        ];

        // 14. 学生限定（カテゴリ7: 学校）
        $rooms[] = [
            'name' => '高校生限定雑談',
            'description' => '高校生だけの部屋',
            'img_url' => 'https://example.com/highschool.jpg',
            'member' => 4200,
            'emid' => 'highschool_001',
            'category' => 7,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => '中学生限定トーク',
            'description' => '中学生だけのグループ',
            'img_url' => 'https://example.com/jrhighschool.jpg',
            'member' => 3800,
            'emid' => 'jrhighschool_001',
            'category' => 7,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => '中高生限定',
            'description' => '中学生、高校生限定の部屋',
            'img_url' => 'https://example.com/jrhigh.jpg',
            'member' => 5200,
            'emid' => 'jrhigh_001',
            'category' => 7,
            'updated_at' => $now
        ];

        // 15. 地雷系・界隈系（カテゴリ8: 趣味）
        $rooms[] = [
            'name' => '地雷系量産型',
            'description' => '地雷系や量産型好きの部屋',
            'img_url' => 'https://example.com/jirai.jpg',
            'member' => 2700,
            'emid' => 'jirai_001',
            'category' => 8,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => '片目界隈',
            'description' => '片目界隈や自撮り界隈',
            'img_url' => 'https://example.com/katame.jpg',
            'member' => 1900,
            'emid' => 'katame_001',
            'category' => 8,
            'updated_at' => $now
        ];

        // 16. VTuber・配信者関連（カテゴリ26: 芸能人・有名人）
        $rooms[] = [
            'name' => 'にじさんじファン',
            'description' => 'にじさんじライバーについて語ろう',
            'img_url' => 'https://example.com/nijisanji.jpg',
            'member' => 3400,
            'emid' => 'nijisanji_001',
            'category' => 26,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => 'にじさんじなりきり',
            'description' => 'にじさんじライバーでなりきり',
            'img_url' => 'https://example.com/nijisanji_nrkr.jpg',
            'member' => 1050,
            'emid' => 'nijisanji_nrkr_001',
            'category' => 26,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => 'ホロライブ情報',
            'description' => 'ホロライブの最新情報',
            'img_url' => 'https://example.com/hololive.jpg',
            'member' => 3600,
            'emid' => 'hololive_001',
            'category' => 26,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => '加藤純一ファン',
            'description' => '衛門の集い',
            'img_url' => 'https://example.com/kato.jpg',
            'member' => 2900,
            'emid' => 'kato_001',
            'category' => 26,
            'updated_at' => $now
        ];

        // 17. フリーランス・仕事関連（カテゴリ40: 金融・ビジネス）
        $rooms[] = [
            'name' => 'フリーランス交流会',
            'description' => 'フリーランスの情報交換',
            'img_url' => 'https://example.com/freelance.jpg',
            'member' => 2300,
            'emid' => 'freelance_001',
            'category' => 40,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => 'WEBデザイナーの集い',
            'description' => 'WEBデザインの情報共有',
            'img_url' => 'https://example.com/webdesign.jpg',
            'member' => 1700,
            'emid' => 'webdesign_001',
            'category' => 40,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => 'IT資格取得者',
            'description' => '基本情報技術者や応用情報技術者の勉強グループ',
            'img_url' => 'https://example.com/it_cert.jpg',
            'member' => 1500,
            'emid' => 'it_cert_001',
            'category' => 40,
            'updated_at' => $now
        ];

        // 18. 性別限定（カテゴリ8: 趣味）
        $rooms[] = [
            'name' => '女性限定雑談',
            'description' => '女子だけの部屋',
            'img_url' => 'https://example.com/female.jpg',
            'member' => 6000,
            'emid' => 'female_001',
            'category' => 8,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => '男性限定トーク',
            'description' => '男子だけのグループ',
            'img_url' => 'https://example.com/male.jpg',
            'member' => 5500,
            'emid' => 'male_001',
            'category' => 8,
            'updated_at' => $now
        ];

        // 19. 恋愛・人間関係（カテゴリ8: 趣味）
        $rooms[] = [
            'name' => '恋愛相談室',
            'description' => '恋愛の相談に乗ります',
            'img_url' => 'https://example.com/love.jpg',
            'member' => 4500,
            'emid' => 'love_001',
            'category' => 8,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => '失恋から立ち直る会',
            'description' => '失恋や復縁について語ろう',
            'img_url' => 'https://example.com/broken_heart.jpg',
            'member' => 2200,
            'emid' => 'broken_heart_001',
            'category' => 8,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => '偽カップル募集',
            'description' => '偽カプ相手を探そう',
            'img_url' => 'https://example.com/fake_couple.jpg',
            'member' => 1800,
            'emid' => 'fake_couple_001',
            'category' => 8,
            'updated_at' => $now
        ];

        // 20. 趣味・エンタメ（カテゴリ8: 趣味、カテゴリ33: 音楽）
        $rooms[] = [
            'name' => 'カラオケ好き集まれ',
            'description' => 'カラオケの話をしよう',
            'img_url' => 'https://example.com/karaoke.jpg',
            'member' => 3300,
            'emid' => 'karaoke_001',
            'category' => 33,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => '鉄道ファン',
            'description' => '電車や列車が好きな人の部屋',
            'img_url' => 'https://example.com/train.jpg',
            'member' => 2800,
            'emid' => 'train_001',
            'category' => 8,
            'updated_at' => $now
        ];

        $rooms[] = [
            'name' => 'ガンプラ制作',
            'description' => 'ガンダムのプラモデル作り',
            'img_url' => 'https://example.com/gunpla.jpg',
            'member' => 2100,
            'emid' => 'gunpla_001',
            'category' => 8,
            'updated_at' => $now
        ];

        // 残りを一般的な雑談ルームで埋める
        for ($i = 1; $i <= 50; $i++) {
            $rooms[] = [
                'name' => "一般雑談部屋 #{$i}",
                'description' => "みんなで楽しく雑談しましょう",
                'img_url' => "https://example.com/chat_{$i}.jpg",
                'member' => rand(100, 5000),
                'emid' => "general_chat_{$i}",
                'category' => 8,
                'updated_at' => $now
            ];
        }

        return $rooms;
    }

    public function testTagApplicationOnInitialRun()
    {
        // 初回実行
        $this->recommendUpdater->updateRecommendTables(false);

        // スタバのタグが適用されているか確認
        $starbucksRooms = DB::$pdo->query(
            "SELECT oc.name, r.tag FROM open_chat oc
             JOIN recommend r ON oc.id = r.id
             WHERE oc.name LIKE '%スタバ%'"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($starbucksRooms, 'スタバ関連のルームにタグが適用されていません');
        $this->assertEquals('スタバ', $starbucksRooms[0]['tag'], 'スタバのタグが正しく適用されていません');

        // ChatGPT / AI関連のタグが適用されているか確認
        $aiRooms = DB::$pdo->query(
            "SELECT oc.name, r.tag FROM open_chat oc
             JOIN recommend r ON oc.id = r.id
             WHERE oc.description LIKE '%ChatGPT%'"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($aiRooms, 'AI関連のルームにタグが適用されていません');
        $this->assertEquals('生成AI・ChatGPT', $aiRooms[0]['tag'], 'AI関連のタグが正しく適用されていません');

        // ポケモンカードのタグが適用されているか確認
        $pokemonCardRooms = DB::$pdo->query(
            "SELECT oc.name, r.tag FROM open_chat oc
             JOIN recommend r ON oc.id = r.id
             WHERE oc.name LIKE '%ポケモンカード%'"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($pokemonCardRooms, 'ポケモンカード関連のルームにタグが適用されていません');
        $this->assertEquals('ポケモンカード（ポケカ）', $pokemonCardRooms[0]['tag'], 'ポケモンカードのタグが正しく適用されていません');

        // プロセカなりきりのタグが適用されているか確認
        $prosekaNrkrRooms = DB::$pdo->query(
            "SELECT oc.name, r.tag FROM open_chat oc
             JOIN recommend r ON oc.id = r.id
             WHERE oc.name LIKE '%プロセカなりきり%'"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($prosekaNrkrRooms, 'プロセカなりきり関連のルームにタグが適用されていません');

        // 大学生のタグが適用されているか確認
        $universityRooms = DB::$pdo->query(
            "SELECT oc.name, r.tag FROM open_chat oc
             JOIN recommend r ON oc.id = r.id
             WHERE oc.name LIKE '%大学生%'"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($universityRooms, '大学生関連のルームにタグが適用されていません');
        $this->assertEquals('大学生', $universityRooms[0]['tag'], '大学生のタグが正しく適用されていません');

        // 25卒のタグが適用されているか確認
        $jobHunt25Rooms = DB::$pdo->query(
            "SELECT oc.name, r.tag FROM open_chat oc
             JOIN recommend r ON oc.id = r.id
             WHERE oc.name LIKE '%25卒%'"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($jobHunt25Rooms, '25卒関連のルームにタグが適用されていません');
        $this->assertEquals('25卒', $jobHunt25Rooms[0]['tag'], '25卒のタグが正しく適用されていません');
    }

    public function testTagUpdateAfterRoomModification()
    {
        // 初回実行
        $this->recommendUpdater->updateRecommendTables(false);

        // ルームの名前と説明を変更
        $roomToUpdate = DB::$pdo->query(
            "SELECT id FROM open_chat WHERE name = '一般雑談部屋 #1'"
        )->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($roomToUpdate, '更新対象のルームが見つかりません');

        $roomId = $roomToUpdate['id'];

        // ルームを「プロセカ」関連に変更
        DB::execute(
            "UPDATE open_chat SET name = 'プロセカ初心者部屋', description = 'プロジェクトセカイの初心者向けグループ', updated_at = NOW() WHERE id = :id",
            ['id' => $roomId]
        );

        // 再度タグ更新を実行
        $this->recommendUpdater->updateRecommendTables(false);

        // タグが更新されているか確認
        $updatedRoom = DB::$pdo->prepare(
            "SELECT oc.name, r.tag FROM open_chat oc
             JOIN recommend r ON oc.id = r.id
             WHERE oc.id = ?"
        );
        $updatedRoom->execute([$roomId]);
        $result = $updatedRoom->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($result, '更新後のタグが見つかりません');
        $this->assertEquals('プロジェクトセカイ（プロセカ）', $result['tag'], 'タグが正しく更新されていません');
    }

    public function testMultipleRoomUpdates()
    {
        // 初回実行
        $this->recommendUpdater->updateRecommendTables(false);

        // 複数のルームを変更
        $roomsToUpdate = DB::$pdo->query(
            "SELECT id FROM open_chat WHERE name LIKE '一般雑談部屋%' LIMIT 5"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(5, $roomsToUpdate, '更新対象のルームが5件見つかりません');

        $updates = [
            ['name' => '呪術廻戦ファンクラブ', 'description' => '呪術廻戦について語りましょう', 'expected_tag' => '呪術廻戦'],
            ['name' => 'ChatGPT活用術', 'description' => 'ChatGPTやLLMの使い方を共有', 'expected_tag' => '生成AI・ChatGPT'],
            ['name' => 'MBTI INFP集会', 'description' => 'INFPの人たちが集まる部屋', 'expected_tag' => 'MBTI'],
            ['name' => '高校生限定部屋', 'description' => '高校生だけの雑談', 'expected_tag' => '高校生限定'],
            ['name' => 'にじさんじリスナー', 'description' => 'にじさんじが好きな人の部屋', 'expected_tag' => 'にじさんじ'],
        ];

        foreach ($roomsToUpdate as $index => $room) {
            DB::execute(
                "UPDATE open_chat SET name = :name, description = :description, updated_at = NOW() WHERE id = :id",
                [
                    'name' => $updates[$index]['name'],
                    'description' => $updates[$index]['description'],
                    'id' => $room['id']
                ]
            );
        }

        // 再度タグ更新を実行
        $this->recommendUpdater->updateRecommendTables(false);

        // すべてのルームのタグが正しく更新されているか確認
        foreach ($roomsToUpdate as $index => $room) {
            $stmt = DB::$pdo->prepare(
                "SELECT oc.name, r.tag FROM open_chat oc
                 JOIN recommend r ON oc.id = r.id
                 WHERE oc.id = ?"
            );
            $stmt->execute([$room['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->assertNotEmpty($result, "ルームID {$room['id']} のタグが見つかりません");
            $this->assertEquals(
                $updates[$index]['expected_tag'],
                $result['tag'],
                "ルーム「{$updates[$index]['name']}」のタグが正しく更新されていません"
            );
        }
    }

    public function testOcTagAndOcTag2Tables()
    {
        // 初回実行
        $this->recommendUpdater->updateRecommendTables(false);

        // oc_tag テーブルにタグが適用されているか確認
        $ocTagCount = DB::$pdo->query("SELECT COUNT(*) FROM oc_tag")->fetchColumn();
        $this->assertGreaterThan(0, $ocTagCount, 'oc_tagテーブルにタグが適用されていません');

        // oc_tag2 テーブルにタグが適用されているか確認
        $ocTag2Count = DB::$pdo->query("SELECT COUNT(*) FROM oc_tag2")->fetchColumn();
        $this->assertGreaterThan(0, $ocTag2Count, 'oc_tag2テーブルにタグが適用されていません');

        // oc_tag と oc_tag2 で重複していないことを確認
        $duplicates = DB::$pdo->query(
            "SELECT t1.id FROM oc_tag t1
             JOIN oc_tag2 t2 ON t1.id = t2.id AND t1.tag = t2.tag"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEmpty($duplicates, 'oc_tagとoc_tag2で重複したタグが見つかりました');
    }
}

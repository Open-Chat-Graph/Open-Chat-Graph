<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Models/CommentRepositories/test/RecentCommentListRepositoryTest.php
 */

declare(strict_types=1);

use App\Models\CommentRepositories\CommentDB;
use App\Models\CommentRepositories\RecentCommentListRepository;
use App\Models\Repositories\DB;
use PHPUnit\Framework\TestCase;

class RecentCommentListRepositoryTest extends TestCase
{
    private RecentCommentListRepository $repo;
    private \PDO $commentPdo;
    private \PDO $mainPdo;
    private ?\PDO $originalCommentPdo;
    private ?\PDO $originalMainPdo;

    protected function setUp(): void
    {
        $this->originalCommentPdo = CommentDB::$pdo;
        $this->originalMainPdo = DB::$pdo;

        // CommentDB用（comment, log テーブル）
        $this->commentPdo = new \PDO('sqlite::memory:');
        $this->commentPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->commentPdo->exec("
            CREATE TABLE comment (
                comment_id INTEGER PRIMARY KEY AUTOINCREMENT,
                open_chat_id INTEGER NOT NULL,
                user_id TEXT NOT NULL DEFAULT '',
                flag INTEGER NOT NULL DEFAULT 0,
                name TEXT NOT NULL DEFAULT '',
                text TEXT NOT NULL DEFAULT '',
                time TEXT NOT NULL DEFAULT '2025-01-01 00:00:00'
            )
        ");

        $this->commentPdo->exec("
            CREATE TABLE log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                entity_id INTEGER,
                ip TEXT NOT NULL DEFAULT '',
                data TEXT
            )
        ");

        // SQLiteにはGREATEST関数がないためUDFで補完
        $this->commentPdo->sqliteCreateFunction('GREATEST', fn($a, $b) => max($a, $b), 2);

        // DB用（open_chat テーブル）
        $this->mainPdo = new \PDO('sqlite::memory:');
        $this->mainPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->mainPdo->exec("
            CREATE TABLE open_chat (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL DEFAULT '',
                img_url TEXT NOT NULL DEFAULT '',
                category INTEGER NOT NULL DEFAULT 0,
                member INTEGER NOT NULL DEFAULT 0,
                emblem INTEGER NOT NULL DEFAULT 0
            )
        ");

        CommentDB::$pdo = $this->commentPdo;
        DB::$pdo = $this->mainPdo;

        $this->repo = new RecentCommentListRepository();
    }

    protected function tearDown(): void
    {
        CommentDB::$pdo = $this->originalCommentPdo;
        DB::$pdo = $this->originalMainPdo;
    }

    private function insertComment(int $openChatId, int $flag, string $userId = 'user1', string $name = 'テスト', string $text = 'テキスト', string $time = '2025-01-01 00:00:00'): int
    {
        $stmt = $this->commentPdo->prepare(
            "INSERT INTO comment (open_chat_id, user_id, flag, name, text, time) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$openChatId, $userId, $flag, $name, $text, $time]);
        return (int) $this->commentPdo->lastInsertId();
    }

    private function insertOpenChat(int $id, string $name = 'テストOC', string $imgUrl = '/img.png', int $member = 100): void
    {
        $stmt = $this->mainPdo->prepare(
            "INSERT INTO open_chat (id, name, img_url, category, member, emblem) VALUES (?, ?, ?, 0, ?, 0)"
        );
        $stmt->execute([$id, $name, $imgUrl, $member]);
    }

    // -------------------------------------------------------------------
    // findRecentCommentOpenChatAll: open_chat_id=0 のflagフィルタリング
    // -------------------------------------------------------------------

    public function testPolicyComment_flag0_showsNameAndText(): void
    {
        $this->insertComment(0, 0, 'user1', '表示ユーザー', '表示テキスト', '2025-01-01 00:01:00');

        $result = $this->repo->findRecentCommentOpenChatAll(0, 10);

        $this->assertCount(1, $result);
        $this->assertSame(0, $result[0]['id']);
        $this->assertSame('表示ユーザー', $result[0]['user']);
        $this->assertSame('表示テキスト', $result[0]['description']);
    }

    public function testPolicyComment_flag4_showsNameAndText(): void
    {
        $this->insertComment(0, 4, 'user1', '画像削除ユーザー', '画像削除テキスト', '2025-01-01 00:01:00');

        $result = $this->repo->findRecentCommentOpenChatAll(0, 10);

        $this->assertCount(1, $result);
        $this->assertSame('画像削除ユーザー', $result[0]['user']);
        $this->assertSame('画像削除テキスト', $result[0]['description']);
    }

    public function testPolicyComment_flag5_masksNameAndText(): void
    {
        $this->insertComment(0, 5, 'user1', '削除ユーザー', '削除テキスト', '2025-01-01 00:01:00');

        $result = $this->repo->findRecentCommentOpenChatAll(0, 10);

        $this->assertCount(1, $result);
        $this->assertSame('***', $result[0]['user']);
        $this->assertSame('', $result[0]['description']);
    }

    public function testPolicyComment_flag2_masksNameAndText(): void
    {
        $this->insertComment(0, 2, 'user1', '通報ユーザー', '通報テキスト', '2025-01-01 00:01:00');

        $result = $this->repo->findRecentCommentOpenChatAll(0, 10);

        $this->assertCount(1, $result);
        $this->assertSame('***', $result[0]['user']);
        $this->assertSame('', $result[0]['description']);
    }

    public function testPolicyComment_emptyName_showsAnonymous(): void
    {
        $this->insertComment(0, 0, 'user1', '', 'テキスト', '2025-01-01 00:01:00');

        $result = $this->repo->findRecentCommentOpenChatAll(0, 10);

        $this->assertCount(1, $result);
        $this->assertSame('匿名', $result[0]['user']);
    }

    // -------------------------------------------------------------------
    // findRecentCommentOpenChatAll: 通常OC（open_chat_id > 0）のflagフィルタリング
    // -------------------------------------------------------------------

    public function testNormalComment_flag0_showsNameAndText(): void
    {
        $this->insertOpenChat(1, 'テストOC');
        $this->insertComment(1, 0, 'user1', 'ユーザー', 'テキスト', '2025-01-01 00:01:00');

        $result = $this->repo->findRecentCommentOpenChatAll(0, 10);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame('ユーザー', $result[0]['user']);
        $this->assertSame('テキスト', $result[0]['description']);
    }

    public function testNormalComment_flag5_masksNameAndText(): void
    {
        $this->insertOpenChat(1, 'テストOC');
        $this->insertComment(1, 5, 'user1', '削除ユーザー', '削除テキスト', '2025-01-01 00:01:00');

        $result = $this->repo->findRecentCommentOpenChatAll(0, 10);

        $this->assertCount(1, $result);
        $this->assertSame('***', $result[0]['user']);
        $this->assertSame('', $result[0]['description']);
    }

    public function testNormalComment_flag4_showsNameAndText(): void
    {
        $this->insertOpenChat(1, 'テストOC');
        $this->insertComment(1, 4, 'user1', '画像削除ユーザー', '画像削除テキスト', '2025-01-01 00:01:00');

        $result = $this->repo->findRecentCommentOpenChatAll(0, 10);

        $this->assertCount(1, $result);
        $this->assertSame('画像削除ユーザー', $result[0]['user']);
        $this->assertSame('画像削除テキスト', $result[0]['description']);
    }

    // -------------------------------------------------------------------
    // findRecentCommentOpenChatAll: flag=1（シャドウ削除）は本人のみ表示
    // -------------------------------------------------------------------

    public function testShadowDeleted_hiddenFromOtherUsers(): void
    {
        $this->insertOpenChat(1, 'テストOC');
        $this->insertComment(1, 1, 'author1', 'シャドウ', 'テキスト', '2025-01-01 00:01:00');

        // 別ユーザーとして取得 → flag=1 は WHERE で除外される
        $result = $this->repo->findRecentCommentOpenChatAll(0, 10, '', 'other_user');

        $this->assertCount(0, $result);
    }

    public function testShadowDeleted_visibleToAuthor(): void
    {
        $this->insertOpenChat(1, 'テストOC');
        $this->insertComment(1, 1, 'author1', 'シャドウ', 'テキスト', '2025-01-01 00:01:00');

        // 本人として取得 → flag=1 でも表示、CASEでflag=0に変換
        $result = $this->repo->findRecentCommentOpenChatAll(0, 10, '', 'author1');

        $this->assertCount(1, $result);
        $this->assertSame('シャドウ', $result[0]['user']);
        $this->assertSame('テキスト', $result[0]['description']);
    }

    // -------------------------------------------------------------------
    // findRecentCommentOpenChatAll: 混在テスト（policy + 通常OC）
    // -------------------------------------------------------------------

    public function testMixedComments_policyAndNormal(): void
    {
        $this->insertOpenChat(1, 'テストOC');

        // policy: flag=0（表示）, flag=5（マスク）
        $this->insertComment(0, 0, 'user1', 'policy正常', 'pテキスト', '2025-01-01 00:03:00');
        $this->insertComment(0, 5, 'user2', 'policy削除', 'p削除テキスト', '2025-01-01 00:02:00');

        // 通常: flag=0（表示）
        $this->insertComment(1, 0, 'user3', '通常ユーザー', '通常テキスト', '2025-01-01 00:01:00');

        $result = $this->repo->findRecentCommentOpenChatAll(0, 10);

        $this->assertCount(3, $result);

        // DESC順（時刻の降順）
        $this->assertSame('policy正常', $result[0]['user']);
        $this->assertSame('pテキスト', $result[0]['description']);
        $this->assertSame(0, $result[0]['id']); // policy

        $this->assertSame('***', $result[1]['user']);
        $this->assertSame('', $result[1]['description']);
        $this->assertSame(0, $result[1]['id']); // policy masked

        $this->assertSame('通常ユーザー', $result[2]['user']);
        $this->assertSame('通常テキスト', $result[2]['description']);
        $this->assertSame(1, $result[2]['id']); // 通常OC
    }

    // -------------------------------------------------------------------
    // findRecentCommentOpenChatAll: admin投稿の除外
    // -------------------------------------------------------------------

    public function testAdminCommentsExcluded(): void
    {
        $this->insertComment(0, 0, 'admin_id', '管理者', 'admin投稿', '2025-01-01 00:01:00');
        $this->insertComment(0, 0, 'user1', '一般', '一般投稿', '2025-01-01 00:02:00');

        $result = $this->repo->findRecentCommentOpenChatAll(0, 10, 'admin_id');

        $this->assertCount(1, $result);
        $this->assertSame('一般', $result[0]['user']);
    }

    // -------------------------------------------------------------------
    // getRecordCount
    // -------------------------------------------------------------------

    public function testGetRecordCount_excludesAdminAndShadowDeleted(): void
    {
        $this->insertComment(0, 0, 'user1');
        $this->insertComment(0, 0, 'admin_id');
        $this->insertComment(0, 1, 'user2'); // シャドウ削除
        $this->insertComment(0, 5, 'user3'); // 通常削除（表示対象）

        // admin除外、flag=1はuser_id一致時のみカウント
        $count = $this->repo->getRecordCount('admin_id', 'user2');

        // user1のflag=0 + user2のflag=1（本人） + user3のflag=5 = 3
        $this->assertSame(3, $count);
    }

    // -------------------------------------------------------------------
    // getLatestCommentTime
    // -------------------------------------------------------------------

    public function testGetLatestCommentTime_returnsLatest(): void
    {
        $this->insertComment(0, 0, 'user1', '', '', '2025-01-01 00:00:00');
        $this->insertComment(0, 0, 'user2', '', '', '2025-06-15 12:30:00');

        $time = $this->repo->getLatestCommentTime();

        $this->assertSame('2025-06-15 12:30:00', $time);
    }

    public function testGetLatestCommentTime_considersAdminLogTime(): void
    {
        $this->insertComment(0, 0, 'user1', '', '', '2025-01-01 00:00:00');

        $stmt = $this->commentPdo->prepare("INSERT INTO log (type, data) VALUES (?, ?)");
        $stmt->execute(['AdminDeleteComment', '2025-12-31 23:59:59']);

        $time = $this->repo->getLatestCommentTime();

        $this->assertSame('2025-12-31 23:59:59', $time);
    }

    public function testGetLatestCommentTime_returnsZeroStringWhenEmpty(): void
    {
        $time = $this->repo->getLatestCommentTime();

        // 両テーブル空 → GREATEST(COALESCE(NULL,'0'), COALESCE(NULL,'0')) = '0'
        $this->assertSame('0', $time);
    }
}

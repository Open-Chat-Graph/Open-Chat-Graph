<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Models/CommentRepositories/test/DeleteCommentRepositoryTest.php
 */

declare(strict_types=1);

use App\Models\CommentRepositories\CommentDB;
use App\Models\CommentRepositories\DeleteCommentRepository;
use PHPUnit\Framework\TestCase;

class DeleteCommentRepositoryTest extends TestCase
{
    private DeleteCommentRepository $repo;
    private \PDO $pdo;
    private ?\PDO $originalPdo;

    protected function setUp(): void
    {
        $this->originalPdo = CommentDB::$pdo;

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE comment (
                comment_id INTEGER PRIMARY KEY AUTOINCREMENT,
                open_chat_id INTEGER NOT NULL,
                user_id TEXT NOT NULL DEFAULT '',
                flag INTEGER NOT NULL DEFAULT 0,
                name TEXT NOT NULL DEFAULT '',
                text TEXT NOT NULL DEFAULT ''
            )
        ");

        $this->pdo->exec("
            CREATE TABLE comment_image (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                comment_id INTEGER NOT NULL,
                filename TEXT NOT NULL
            )
        ");

        $this->pdo->exec("
            CREATE TABLE `like` (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                comment_id INTEGER NOT NULL,
                user_id TEXT NOT NULL DEFAULT ''
            )
        ");

        $this->pdo->exec("
            CREATE TABLE log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                entity_id INTEGER,
                ip TEXT NOT NULL DEFAULT '',
                data TEXT
            )
        ");

        CommentDB::$pdo = $this->pdo;
        $this->repo = new DeleteCommentRepository();
    }

    protected function tearDown(): void
    {
        CommentDB::$pdo = $this->originalPdo;
    }

    private function insertComment(int $openChatId, int $flag, string $userId = 'user1', string $name = '', string $text = ''): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO comment (open_chat_id, user_id, flag, name, text) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$openChatId, $userId, $flag, $name, $text]);
        return (int) $this->pdo->lastInsertId();
    }

    private function insertCommentImage(int $commentId, string $filename): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO comment_image (comment_id, filename) VALUES (?, ?)");
        $stmt->execute([$commentId, $filename]);
    }

    private function insertLog(string $type, int $entityId, string $ip = '127.0.0.1'): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO log (type, entity_id, ip) VALUES (?, ?, ?)");
        $stmt->execute([$type, $entityId, $ip]);
    }

    private function getFlag(int $commentId): int
    {
        $stmt = $this->pdo->prepare("SELECT flag FROM comment WHERE comment_id = ?");
        $stmt->execute([$commentId]);
        return (int) $stmt->fetchColumn();
    }

    // -------------------------------------------------------------------
    // restoreDeletedComments: flag=1,2,4,5 を全て flag=0 に復元する
    // -------------------------------------------------------------------

    public function testRestoreDeletedComments_restoresAllDeletedFlags(): void
    {
        $ocId = 100;
        $id0 = $this->insertComment($ocId, 0); // 通常
        $id1 = $this->insertComment($ocId, 1); // シャドウ削除
        $id2 = $this->insertComment($ocId, 2); // 通報
        $id3 = $this->insertComment($ocId, 3); // 完全削除用
        $id4 = $this->insertComment($ocId, 4); // 画像削除
        $id5 = $this->insertComment($ocId, 5); // 通常削除

        $count = $this->repo->restoreDeletedComments($ocId);

        $this->assertSame(4, $count); // flag=1,2,4,5 の4件
        $this->assertSame(0, $this->getFlag($id0)); // 変更なし
        $this->assertSame(0, $this->getFlag($id1)); // 復元
        $this->assertSame(0, $this->getFlag($id2)); // 復元
        $this->assertSame(3, $this->getFlag($id3)); // 変更なし
        $this->assertSame(0, $this->getFlag($id4)); // 復元
        $this->assertSame(0, $this->getFlag($id5)); // 復元
    }

    public function testRestoreDeletedComments_doesNotAffectOtherOpenChat(): void
    {
        $id1 = $this->insertComment(100, 1);
        $id2 = $this->insertComment(200, 1);

        $this->repo->restoreDeletedComments(100);

        $this->assertSame(0, $this->getFlag($id1));
        $this->assertSame(1, $this->getFlag($id2)); // 別OCは変更なし
    }

    // -------------------------------------------------------------------
    // restoreSoftDeletedComments: flag=5 のみ flag=0 に復元する
    // -------------------------------------------------------------------

    public function testRestoreSoftDeletedComments_onlyRestoresFlag5(): void
    {
        $ocId = 100;
        $id1 = $this->insertComment($ocId, 1);
        $id2 = $this->insertComment($ocId, 2);
        $id4 = $this->insertComment($ocId, 4);
        $id5 = $this->insertComment($ocId, 5);

        $count = $this->repo->restoreSoftDeletedComments($ocId);

        $this->assertSame(1, $count);
        $this->assertSame(1, $this->getFlag($id1)); // 変更なし
        $this->assertSame(2, $this->getFlag($id2)); // 変更なし
        $this->assertSame(4, $this->getFlag($id4)); // 変更なし
        $this->assertSame(0, $this->getFlag($id5)); // 復元
    }

    // -------------------------------------------------------------------
    // getDeletedCommentIds: flag=1,2,4,5 のコメントIDを取得
    // -------------------------------------------------------------------

    public function testGetDeletedCommentIds_returnsAllDeletedFlags(): void
    {
        $ocId = 100;
        $id0 = $this->insertComment($ocId, 0);
        $id1 = $this->insertComment($ocId, 1);
        $id2 = $this->insertComment($ocId, 2);
        $id3 = $this->insertComment($ocId, 3);
        $id4 = $this->insertComment($ocId, 4);
        $id5 = $this->insertComment($ocId, 5);

        $ids = $this->repo->getDeletedCommentIds($ocId);

        $this->assertContains($id1, $ids);
        $this->assertContains($id2, $ids);
        $this->assertContains($id4, $ids);
        $this->assertContains($id5, $ids);
        $this->assertNotContains($id0, $ids);
        $this->assertNotContains($id3, $ids);
        $this->assertCount(4, $ids);
    }

    // -------------------------------------------------------------------
    // getSoftDeletedCommentIds: flag=5 のコメントIDのみ取得
    // -------------------------------------------------------------------

    public function testGetSoftDeletedCommentIds_onlyReturnsFlag5(): void
    {
        $ocId = 100;
        $id1 = $this->insertComment($ocId, 1);
        $id5 = $this->insertComment($ocId, 5);

        $ids = $this->repo->getSoftDeletedCommentIds($ocId);

        $this->assertContains($id5, $ids);
        $this->assertNotContains($id1, $ids);
        $this->assertCount(1, $ids);
    }

    // -------------------------------------------------------------------
    // getDeletedCommentImageFilenames: flag=1,2,4,5 の画像ファイル名を取得
    // -------------------------------------------------------------------

    public function testGetDeletedCommentImageFilenames_returnsAllDeletedFlags(): void
    {
        $ocId = 100;
        $id0 = $this->insertComment($ocId, 0);
        $id1 = $this->insertComment($ocId, 1);
        $id2 = $this->insertComment($ocId, 2);
        $id3 = $this->insertComment($ocId, 3);
        $id4 = $this->insertComment($ocId, 4);
        $id5 = $this->insertComment($ocId, 5);

        $this->insertCommentImage($id0, 'normal.webp');
        $this->insertCommentImage($id1, 'shadow.webp');
        $this->insertCommentImage($id2, 'reported.webp');
        $this->insertCommentImage($id3, 'flag3.webp');
        $this->insertCommentImage($id4, 'imgdel.webp');
        $this->insertCommentImage($id5, 'deleted.webp');

        $filenames = $this->repo->getDeletedCommentImageFilenames($ocId);

        $this->assertContains('shadow.webp', $filenames);
        $this->assertContains('reported.webp', $filenames);
        $this->assertContains('imgdel.webp', $filenames);
        $this->assertContains('deleted.webp', $filenames);
        $this->assertNotContains('normal.webp', $filenames);
        $this->assertNotContains('flag3.webp', $filenames);
        $this->assertCount(4, $filenames);
    }

    // -------------------------------------------------------------------
    // getSoftDeletedCommentImageFilenames: flag=5 の画像ファイル名のみ取得
    // -------------------------------------------------------------------

    public function testGetSoftDeletedCommentImageFilenames_onlyReturnsFlag5(): void
    {
        $ocId = 100;
        $id1 = $this->insertComment($ocId, 1);
        $id5 = $this->insertComment($ocId, 5);

        $this->insertCommentImage($id1, 'shadow.webp');
        $this->insertCommentImage($id5, 'deleted.webp');

        $filenames = $this->repo->getSoftDeletedCommentImageFilenames($ocId);

        $this->assertContains('deleted.webp', $filenames);
        $this->assertNotContains('shadow.webp', $filenames);
        $this->assertCount(1, $filenames);
    }

    // -------------------------------------------------------------------
    // softDeleteAllComments: flag=0 をflag=5に、flag=2,4,5 は除外
    // -------------------------------------------------------------------

    public function testSoftDeleteAllComments_excludesFlag2And4And5(): void
    {
        $ocId = 100;
        $id0 = $this->insertComment($ocId, 0);
        $id1 = $this->insertComment($ocId, 1);
        $id2 = $this->insertComment($ocId, 2);
        $id4 = $this->insertComment($ocId, 4);
        $id5 = $this->insertComment($ocId, 5);

        $count = $this->repo->softDeleteAllComments($ocId);

        $this->assertSame(2, $count); // flag=0,1 の2件
        $this->assertSame(5, $this->getFlag($id0)); // flag=5に変更
        $this->assertSame(5, $this->getFlag($id1)); // flag=5に変更
        $this->assertSame(2, $this->getFlag($id2)); // 変更なし
        $this->assertSame(4, $this->getFlag($id4)); // 変更なし
        $this->assertSame(5, $this->getFlag($id5)); // 変更なし（既にflag=5）
    }

    // -------------------------------------------------------------------
    // shadowDeleteAllComments: flag=0,5等をflag=1に、flag=1,2,4 は除外
    // -------------------------------------------------------------------

    public function testShadowDeleteAllComments_excludesFlag1And2And4(): void
    {
        $ocId = 100;
        $id0 = $this->insertComment($ocId, 0);
        $id1 = $this->insertComment($ocId, 1);
        $id2 = $this->insertComment($ocId, 2);
        $id4 = $this->insertComment($ocId, 4);
        $id5 = $this->insertComment($ocId, 5);

        $count = $this->repo->shadowDeleteAllComments($ocId);

        $this->assertSame(2, $count); // flag=0,5 の2件
        $this->assertSame(1, $this->getFlag($id0)); // flag=1に変更
        $this->assertSame(1, $this->getFlag($id1)); // 変更なし（既にflag=1）
        $this->assertSame(2, $this->getFlag($id2)); // 変更なし
        $this->assertSame(4, $this->getFlag($id4)); // 変更なし
        $this->assertSame(1, $this->getFlag($id5)); // flag=1に変更
    }

    // -------------------------------------------------------------------
    // deleteComment: flag指定でコメントのフラグを更新
    // -------------------------------------------------------------------

    public function testDeleteComment_updatesFlagWhenFlagProvided(): void
    {
        $id = $this->insertComment(100, 0, 'user1');
        $this->insertLog('AddComment', $id, '192.168.1.1');

        $result = $this->repo->deleteComment($id, 1);

        $this->assertIsArray($result);
        $this->assertSame('user1', $result['user_id']);
        $this->assertSame('192.168.1.1', $result['ip']);
        $this->assertSame(1, $this->getFlag($id));
    }

    public function testDeleteComment_deletesRowWhenFlagNull(): void
    {
        $id = $this->insertComment(100, 0, 'user1');
        $this->insertLog('AddComment', $id);

        $result = $this->repo->deleteComment($id, null);

        $this->assertIsArray($result);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM comment WHERE comment_id = ?");
        $stmt->execute([$id]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    public function testDeleteComment_returnsFalseForNonexistent(): void
    {
        $result = $this->repo->deleteComment(99999, 1);
        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------
    // getCommentIdsByOpenChatId: excludeFlags で指定したflagを除外
    // -------------------------------------------------------------------

    public function testGetCommentIdsByOpenChatId_excludesSpecifiedFlags(): void
    {
        $ocId = 100;
        $id0 = $this->insertComment($ocId, 0);
        $id1 = $this->insertComment($ocId, 1);
        $id2 = $this->insertComment($ocId, 2);

        $ids = $this->repo->getCommentIdsByOpenChatId($ocId, [1, 2]);

        $this->assertContains($id0, $ids);
        $this->assertNotContains($id1, $ids);
        $this->assertNotContains($id2, $ids);
    }

    public function testGetCommentIdsByOpenChatId_returnsAllWhenNoExclusions(): void
    {
        $ocId = 100;
        $id0 = $this->insertComment($ocId, 0);
        $id1 = $this->insertComment($ocId, 1);

        $ids = $this->repo->getCommentIdsByOpenChatId($ocId, []);

        $this->assertCount(2, $ids);
    }

    // -------------------------------------------------------------------
    // getCommentImageFilenames: 全flagの画像ファイル名を取得
    // -------------------------------------------------------------------

    public function testGetCommentImageFilenames_returnsAllRegardlessOfFlag(): void
    {
        $ocId = 100;
        $id0 = $this->insertComment($ocId, 0);
        $id1 = $this->insertComment($ocId, 1);
        $id5 = $this->insertComment($ocId, 5);

        $this->insertCommentImage($id0, 'a.webp');
        $this->insertCommentImage($id1, 'b.webp');
        $this->insertCommentImage($id5, 'c.webp');

        $filenames = $this->repo->getCommentImageFilenames($ocId);

        $this->assertCount(3, $filenames);
        $this->assertContains('a.webp', $filenames);
        $this->assertContains('b.webp', $filenames);
        $this->assertContains('c.webp', $filenames);
    }

    // -------------------------------------------------------------------
    // deleteCommentsAll: 全コメント・画像・いいねを物理削除
    // -------------------------------------------------------------------

    public function testDeleteCommentsAll_removesEverything(): void
    {
        $ocId = 100;
        $id1 = $this->insertComment($ocId, 0);
        $id2 = $this->insertComment($ocId, 1);
        $this->insertCommentImage($id1, 'img1.webp');
        $this->insertCommentImage($id2, 'img2.webp');

        $stmt = $this->pdo->prepare("INSERT INTO `like` (comment_id, user_id) VALUES (?, ?)");
        $stmt->execute([$id1, 'liker1']);

        $filenames = $this->repo->deleteCommentsAll($ocId);

        $this->assertCount(2, $filenames);
        $this->assertContains('img1.webp', $filenames);
        $this->assertContains('img2.webp', $filenames);

        // コメント・画像・いいねが全て削除されている
        $this->assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM comment WHERE open_chat_id = 100")->fetchColumn());
        $this->assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM comment_image")->fetchColumn());
        $this->assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM `like`")->fetchColumn());
    }

    // -------------------------------------------------------------------
    // getCommentId: open_chat_id + id からコメントIDを取得
    // -------------------------------------------------------------------

    public function testGetCommentId_returnsCommentId(): void
    {
        // id列はcomment_idのエイリアスとして使うためテーブルにid列を追加
        // 実DBではopen_chat_id内の連番だが、テストでは comment_id を使用
        $this->pdo->exec("DROP TABLE comment");
        $this->pdo->exec("
            CREATE TABLE comment (
                comment_id INTEGER PRIMARY KEY AUTOINCREMENT,
                open_chat_id INTEGER NOT NULL,
                id INTEGER NOT NULL DEFAULT 0,
                user_id TEXT NOT NULL DEFAULT '',
                flag INTEGER NOT NULL DEFAULT 0,
                name TEXT NOT NULL DEFAULT '',
                text TEXT NOT NULL DEFAULT ''
            )
        ");

        $stmt = $this->pdo->prepare(
            "INSERT INTO comment (open_chat_id, id, user_id, flag) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([100, 1, 'user1', 0]);
        $commentId = (int) $this->pdo->lastInsertId();

        $result = $this->repo->getCommentId(100, 1);
        $this->assertSame($commentId, $result);
    }

    public function testGetCommentId_returnsFalseWhenNotFound(): void
    {
        $result = $this->repo->getCommentId(999, 999);
        $this->assertFalse($result);
    }
}

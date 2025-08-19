<?php

declare(strict_types=1);

use App\Models\Repositories\DeleteOpenChatRepository;
use App\Models\Repositories\DeleteOpenChatRepositoryInterface;
use App\Models\Repositories\DB;
use PHPUnit\Framework\TestCase;

class DeleteOpenChatRepositoryTest extends TestCase
{
    private DeleteOpenChatRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(DeleteOpenChatRepositoryInterface::class);
        DB::connect();
    }

    public function testInsertDeletedOpenChat(): void
    {
        $openChatId = 12345;
        $emid = 'test_emid_' . time();

        // Test insertion
        $this->repository->insertDeletedOpenChat($openChatId, $emid);

        // Verify the record was inserted
        $stmt = DB::$pdo->prepare(
            "SELECT * FROM open_chat_deleted WHERE id = :id AND emid = :emid"
        );
        $stmt->execute(['id' => $openChatId, 'emid' => $emid]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotEmpty($result);
        $this->assertEquals($openChatId, (int)$result['id']);
        $this->assertEquals($emid, $result['emid']);

        // Clean up - remove the test record
        $stmt = DB::$pdo->prepare(
            "DELETE FROM open_chat_deleted WHERE id = :id AND emid = :emid"
        );
        $stmt->execute(['id' => $openChatId, 'emid' => $emid]);
    }

    public function testInsertDeletedOpenChatIgnoresDuplicates(): void
    {
        $openChatId = 12346;
        $emid = 'test_emid_duplicate_' . time();

        // Insert first time
        $this->repository->insertDeletedOpenChat($openChatId, $emid);

        // Insert same record again (should be ignored due to INSERT IGNORE)
        $this->repository->insertDeletedOpenChat($openChatId, $emid);

        // Verify only one record exists
        $stmt = DB::$pdo->prepare(
            "SELECT COUNT(*) as count FROM open_chat_deleted WHERE id = :id AND emid = :emid"
        );
        $stmt->execute(['id' => $openChatId, 'emid' => $emid]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals(1, (int)$result['count']);

        // Clean up
        $stmt = DB::$pdo->prepare(
            "DELETE FROM open_chat_deleted WHERE id = :id AND emid = :emid"
        );
        $stmt->execute(['id' => $openChatId, 'emid' => $emid]);
    }
}
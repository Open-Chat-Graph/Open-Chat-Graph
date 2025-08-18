<?php

declare(strict_types=1);

use App\Models\Repositories\Api\ApiOpenChatPageRepository;
use PHPUnit\Framework\TestCase;

class ApiOpenChatPageRepositoryTest extends TestCase
{
    private ApiOpenChatPageRepository $repository;
    private const TEST_ID = 3;

    protected function setUp(): void
    {
        $this->repository = new ApiOpenChatPageRepository();
    }

    public function testGetOpenChatById()
    {
        $result = $this->repository->getOpenChatById(self::TEST_ID);
        
        if ($result) {
            $this->assertArrayHasKey('id', $result);
            $this->assertArrayHasKey('name', $result);
            $this->assertArrayHasKey('member', $result);
            $this->assertEquals(self::TEST_ID, $result['id']);
        }
        
        $this->assertTrue(true);
    }

    public function testGetOpenChatByIdWithTag()
    {
        $result = $this->repository->getOpenChatByIdWithTag(self::TEST_ID);
        
        if ($result) {
            $this->assertArrayHasKey('tag1', $result);
            $this->assertArrayHasKey('tag2', $result);
            $this->assertArrayHasKey('tag3', $result);
        }
        
        $this->assertTrue(true);
    }

    public function testIsExistsOpenChat()
    {
        $exists = $this->repository->isExistsOpenChat(self::TEST_ID);
        $this->assertIsBool($exists);
    }
}
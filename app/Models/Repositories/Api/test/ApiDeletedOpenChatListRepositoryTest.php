<?php

declare(strict_types=1);

use App\Models\Repositories\Api\ApiDeletedOpenChatListRepository;
use PHPUnit\Framework\TestCase;

class ApiDeletedOpenChatListRepositoryTest extends TestCase
{
    private ApiDeletedOpenChatListRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new ApiDeletedOpenChatListRepository();
    }

    public function testgetDeletedOpenChatList()
    {
        $result = $this->repository->getDeletedOpenChatList('2025-08-13', 20);

        debug($result); // Debugging line to inspect the result

        if ($result) {
            $this->assertArrayHasKey('openchat_id', $result[0]);
            $this->assertArrayHasKey('line_internal_id', $result[0]);
            $this->assertArrayHasKey('profile_image_url', $result[0]);
        }

        $this->assertTrue(true);
    }
}

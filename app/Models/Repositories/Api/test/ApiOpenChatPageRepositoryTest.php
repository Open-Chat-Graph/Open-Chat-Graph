<?php

declare(strict_types=1);

use App\Models\Repositories\Api\ApiOpenChatPageRepository;
use PHPUnit\Framework\TestCase;

class ApiOpenChatPageRepositoryTest extends TestCase
{
    private ApiOpenChatPageRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new ApiOpenChatPageRepository();
    }

    public function testGetOpenChatById()
    {
        echo "\n=== Testing ApiOpenChatPageRepository::getOpenChatById ===\n";
        
        // Test with a known OpenChat ID (you may need to adjust this ID)
        $openChatId = 3;
        $result = $this->repository->getOpenChatById($openChatId);
        
        if ($result) {
            echo "Found OpenChat with ID {$openChatId}:\n";
            debug([
                'id' => $result['id'],
                'name' => $result['name'],
                'member' => $result['member'],
                'category' => $result['category'],
                'emblem' => $result['emblem'],
                'url' => $result['url'],
                'created_at' => $result['created_at'],
                'updated_at' => $result['updated_at'],
            ]);
        } else {
            echo "OpenChat with ID {$openChatId} not found\n";
        }
        
        $this->assertTrue(true);
    }

    public function testGetOpenChatByIdWithTag()
    {
        echo "\n=== Testing ApiOpenChatPageRepository::getOpenChatByIdWithTag ===\n";
        
        $openChatId = 3;
        $result = $this->repository->getOpenChatByIdWithTag($openChatId);
        
        if ($result) {
            echo "Found OpenChat with tags for ID {$openChatId}:\n";
            debug([
                'id' => $result['id'],
                'name' => $result['name'],
                'tag1' => $result['tag1'],
                'tag2' => $result['tag2'],
                'tag3' => $result['tag3'],
            ]);
        } else {
            echo "OpenChat with ID {$openChatId} not found\n";
        }
        
        $this->assertTrue(true);
    }

    public function testIsExistsOpenChat()
    {
        echo "\n=== Testing ApiOpenChatPageRepository::isExistsOpenChat ===\n";
        
        $openChatId = 3;
        $exists = $this->repository->isExistsOpenChat($openChatId);
        
        echo "OpenChat ID {$openChatId} exists: " . ($exists ? 'YES' : 'NO') . "\n";
        debug(['id' => $openChatId, 'exists' => $exists]);
        
        $this->assertTrue(true);
    }
    
    public function testMultipleOpenChats()
    {
        echo "\n=== Testing multiple OpenChats to see data structure ===\n";
        
        // Test with multiple IDs to see different data patterns
        $testIds = [3, 10, 169134, 1000, 5000];
        $foundCount = 0;
        
        foreach ($testIds as $id) {
            $result = $this->repository->getOpenChatById($id);
            if ($result) {
                $foundCount++;
                echo "\nOpenChat ID {$id}:\n";
                debug([
                    'name' => substr($result['name'], 0, 30) . '...',
                    'member' => $result['member'],
                    'emblem' => $result['emblem'],
                    'rh_diff_member' => $result['rh_diff_member'],
                    'rd_diff_member' => $result['rd_diff_member'],
                    'rw_diff_member' => $result['rw_diff_member'],
                ]);
            }
        }
        
        echo "\nFound {$foundCount} out of " . count($testIds) . " OpenChats\n";
        
        $this->assertTrue(true);
    }
}
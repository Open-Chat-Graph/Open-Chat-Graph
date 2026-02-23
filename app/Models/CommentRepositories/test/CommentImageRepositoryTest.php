<?php

declare(strict_types=1);

/**
 * docker compose exec app su -s /bin/bash www-data -c "vendor/bin/phpunit --do-not-cache-result app/Models/CommentRepositories/test/CommentImageRepositoryTest.php"
 */

use App\Models\CommentRepositories\CommentImageRepository;
use PHPUnit\Framework\TestCase;

class CommentImageRepositoryTest extends TestCase
{
    private CommentImageRepository $repo;

    protected function setUp(): void
    {
        $this->repo = app(CommentImageRepository::class);
    }

    public function testAddAndGetImages()
    {
        $commentId = 999999;
        $filenames = ['aaaabbbbccccddddeeeeffffgggg0001.webp', 'aaaabbbbccccddddeeeeffffgggg0002.webp'];

        $this->repo->addImages($commentId, $filenames);
        $images = $this->repo->getImagesByCommentId($commentId);

        $this->assertCount(2, $images);
        $this->assertEquals($filenames[0], $images[0]['filename']);
        $this->assertEquals($filenames[1], $images[1]['filename']);

        // Cleanup
        $this->repo->deleteByCommentId($commentId);
    }

    public function testGetImagesByCommentIds()
    {
        $commentId1 = 999998;
        $commentId2 = 999997;

        $this->repo->addImages($commentId1, ['test1111111111111111111111111111.webp']);
        $this->repo->addImages($commentId2, ['test2222222222222222222222222222.webp', 'test3333333333333333333333333333.webp']);

        $result = $this->repo->getImagesByCommentIds([$commentId1, $commentId2]);

        $this->assertArrayHasKey($commentId1, $result);
        $this->assertArrayHasKey($commentId2, $result);
        $this->assertCount(1, $result[$commentId1]);
        $this->assertCount(2, $result[$commentId2]);

        // 返り値が {id, filename} 形式であることを確認
        $this->assertArrayHasKey('id', $result[$commentId1][0]);
        $this->assertArrayHasKey('filename', $result[$commentId1][0]);
        $this->assertIsInt($result[$commentId1][0]['id']);
        $this->assertEquals('test1111111111111111111111111111.webp', $result[$commentId1][0]['filename']);

        // Cleanup
        $this->repo->deleteByCommentId($commentId1);
        $this->repo->deleteByCommentId($commentId2);
    }

    public function testDeleteByCommentIdReturnsFilenames()
    {
        $commentId = 999996;
        $filenames = ['deleteme11111111111111111111111.webp'];

        $this->repo->addImages($commentId, $filenames);
        $deleted = $this->repo->deleteByCommentId($commentId);

        $this->assertEquals($filenames, $deleted);

        // Verify actually deleted
        $images = $this->repo->getImagesByCommentId($commentId);
        $this->assertCount(0, $images);
    }

    public function testGetImagesByCommentIdsWithEmptyArray()
    {
        $result = $this->repo->getImagesByCommentIds([]);
        $this->assertEmpty($result);
    }
}

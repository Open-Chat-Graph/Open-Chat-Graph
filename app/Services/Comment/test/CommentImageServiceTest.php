<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Comment/test/CommentImageServiceTest.php
 *
 * 実際のGdImageFactory・ImageStoreを使い、ファイルシステムへの書き込み・削除を検証する統合テスト
 */

declare(strict_types=1);

use App\Services\Comment\CommentImageService;
use App\Services\Comment\CommentImageServiceInterface;
use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

class CommentImageServiceTest extends TestCase
{
    private CommentImageServiceInterface $service;
    private string $destDir;

    protected function setUp(): void
    {
        $this->service = app(CommentImageServiceInterface::class);
        $this->destDir = MimimalCmsConfig::$publicDir . '/comment-img';
    }

    private function createTempJpeg(): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_img_');
        $gdImage = imagecreatetruecolor(100, 100);
        $red = imagecolorallocate($gdImage, 255, 0, 0);
        imagefilledrectangle($gdImage, 0, 0, 99, 99, $red);
        imagejpeg($gdImage, $tmpFile, 80);
        return $tmpFile;
    }

    private function makeFileArray(string $tmpFile): array
    {
        return [
            'tmp_name' => $tmpFile,
            'type' => 'image/jpeg',
            'size' => filesize($tmpFile),
        ];
    }

    public function testDestinationDirectoryIsWritable()
    {
        $this->assertDirectoryExists($this->destDir, 'comment-img ディレクトリが存在しない');
        $this->assertTrue(is_writable($this->destDir), 'comment-img ディレクトリに書き込み権限がない');
    }

    public function testProcessAndStoreCreatesRealFiles()
    {
        $tmpFile1 = $this->createTempJpeg();
        $tmpFile2 = $this->createTempJpeg();

        $files = [
            $this->makeFileArray($tmpFile1),
            $this->makeFileArray($tmpFile2),
        ];

        $filenames = $this->service->processAndStore($files);

        $this->assertCount(2, $filenames);
        foreach ($filenames as $filename) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{32}\.webp$/', $filename);

            $filePath = $this->destDir . '/' . $filename;
            $this->assertFileExists($filePath, "保存されたファイルが存在しない: {$filePath}");
            $this->assertGreaterThan(0, filesize($filePath), "ファイルサイズが0: {$filePath}");
        }

        // ファイル名が一意
        $this->assertNotEquals($filenames[0], $filenames[1]);

        // クリーンアップ
        $this->service->deleteImages($filenames);
        foreach ($filenames as $filename) {
            $this->assertFileDoesNotExist($this->destDir . '/' . $filename, 'deleteImagesで削除されていない');
        }

        @unlink($tmpFile1);
        @unlink($tmpFile2);
    }

    public function testProcessAndStoreMaxThreeImages()
    {
        $tmpFiles = [];
        $files = [];
        for ($i = 0; $i < 4; $i++) {
            $tmp = $this->createTempJpeg();
            $tmpFiles[] = $tmp;
            $files[] = $this->makeFileArray($tmp);
        }

        $filenames = $this->service->processAndStore($files);

        $this->assertCount(3, $filenames, '4枚渡しても3枚に制限されるべき');

        // クリーンアップ
        $this->service->deleteImages($filenames);
        foreach ($tmpFiles as $f) @unlink($f);
    }

    public function testDeleteImagesHandlesNonExistentFiles()
    {
        // 存在しないファイルを渡してもエラーにならない
        $this->service->deleteImages(['nonexistent_file_12345.webp']);
        $this->assertTrue(true);
    }
}

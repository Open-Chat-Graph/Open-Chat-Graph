<?php

declare(strict_types=1);

/**
 * docker compose exec app su -s /bin/bash www-data -c "vendor/bin/phpunit app/Controllers/Api/test/CommentPostApiControllerTest.php"
 *
 * CommentPostApiControllerの統合テスト
 * - CommentImageService: 実物（実際にファイルを書き込む）
 * - CommentImageRepository: 実物（実際にDBに保存する）
 * - Auth/reCAPTCHA: モック（外部サービス）
 * - CommentPostRepository: モック（テスト用コメントIDを返す）
 */

use App\Controllers\Api\CommentPostApiController;
use App\Models\CommentRepositories\CommentImageRepositoryInterface;
use App\Models\CommentRepositories\CommentLogRepositoryInterface;
use App\Models\CommentRepositories\CommentPostRepositoryInterface;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Services\Auth\AuthInterface;
use App\Services\Auth\GoogleReCaptcha;
use App\Services\Comment\CommentImageServiceInterface;
use App\Services\Storage\FileStorageInterface;
use Shared\MimimalCmsConfig;
use PHPUnit\Framework\TestCase;

class CommentPostApiControllerTest extends TestCase
{
    private const TEST_COMMENT_ID = 999990;

    private string $destDir;

    protected function setUp(): void
    {
        $this->destDir = MimimalCmsConfig::$publicDir . '/comment-img';
    }

    private function createExternalStubs(): array
    {
        $authStub = $this->createStub(AuthInterface::class);
        $authStub->method('verifyCookieUserId')->willReturn('test_user_id');

        $recaptchaStub = $this->createStub(GoogleReCaptcha::class);
        $recaptchaStub->method('validate')->willReturn(0.9);

        $openChatPageRepo = $this->createStub(OpenChatPageRepositoryInterface::class);
        $openChatPageRepo->method('isExistsOpenChat')->willReturn(true);

        $commentPostRepo = $this->createMock(CommentPostRepositoryInterface::class);
        $commentPostRepo->method('getBanRoomWeek')->willReturn(false);
        $commentPostRepo->method('getBanUser')->willReturn(false);
        $commentPostRepo->method('addComment')->willReturn(self::TEST_COMMENT_ID);

        $commentLogRepo = $this->createStub(CommentLogRepositoryInterface::class);
        $fileStorage = $this->createStub(FileStorageInterface::class);

        return [$authStub, $recaptchaStub, $openChatPageRepo, $commentPostRepo, $commentLogRepo, $fileStorage];
    }

    private function createTempJpeg(): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_img_');
        $gdImage = imagecreatetruecolor(100, 100);
        $color = imagecolorallocate($gdImage, 255, 0, 0);
        imagefilledrectangle($gdImage, 0, 0, 99, 99, $color);
        imagejpeg($gdImage, $tmpFile, 80);
        imagedestroy($gdImage);
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

    /**
     * テスト後のクリーンアップ: DB + ファイル
     */
    private function cleanup(array $filenames): void
    {
        $imageRepo = app(CommentImageRepositoryInterface::class);
        $imageRepo->deleteByCommentId(self::TEST_COMMENT_ID);

        $imageService = app(CommentImageServiceInterface::class);
        $imageService->deleteImages($filenames);
    }

    public function testWithoutImages()
    {
        $inst = app(CommentPostApiController::class);
        [$authStub, $recaptchaStub, $openChatPageRepo, $commentPostRepo, $commentLogRepo, $fileStorage] = $this->createExternalStubs();

        $res = $inst->index(
            $commentPostRepo,
            $commentLogRepo,
            app(CommentImageRepositoryInterface::class),
            app(CommentImageServiceInterface::class),
            $openChatPageRepo,
            $authStub,
            $recaptchaStub,
            $fileStorage,
            'dummy_token',
            1,
            'テストユーザー',
            'テスト本文（画像なし）',
            null,
            null,
            null
        );

        $this->assertNotFalse($res);
        $json = json_decode($res->getBody(), true);
        $this->assertArrayHasKey('images', $json);
        $this->assertEmpty($json['images']);

        $this->cleanup([]);
    }

    public function testWithOneImage()
    {
        $inst = app(CommentPostApiController::class);
        [$authStub, $recaptchaStub, $openChatPageRepo, $commentPostRepo, $commentLogRepo, $fileStorage] = $this->createExternalStubs();

        $tmpFile = $this->createTempJpeg();

        $res = $inst->index(
            $commentPostRepo,
            $commentLogRepo,
            app(CommentImageRepositoryInterface::class),
            app(CommentImageServiceInterface::class),
            $openChatPageRepo,
            $authStub,
            $recaptchaStub,
            $fileStorage,
            'dummy_token',
            1,
            'テストユーザー',
            'テスト本文（画像1枚）',
            $this->makeFileArray($tmpFile),
            null,
            null
        );

        $this->assertNotFalse($res);
        $json = json_decode($res->getBody(), true);
        $this->assertArrayHasKey('images', $json);
        $this->assertCount(1, $json['images']);

        $filename = $json['images'][0];
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}\.webp$/', $filename);

        // 実ファイルが存在するか
        $filePath = $this->destDir . '/' . $filename;
        $this->assertFileExists($filePath, '画像ファイルが実際に保存されていない');
        $this->assertGreaterThan(0, filesize($filePath));

        // DBに保存されているか
        $imageRepo = app(CommentImageRepositoryInterface::class);
        $dbImages = $imageRepo->getImagesByCommentId(self::TEST_COMMENT_ID);
        $this->assertCount(1, $dbImages);
        $this->assertSame($filename, $dbImages[0]['filename']);

        // クリーンアップ
        $this->cleanup($json['images']);
        @unlink($tmpFile);
    }

    public function testWithThreeImages()
    {
        $inst = app(CommentPostApiController::class);
        [$authStub, $recaptchaStub, $openChatPageRepo, $commentPostRepo, $commentLogRepo, $fileStorage] = $this->createExternalStubs();

        $tmpFiles = [];
        $imageFiles = [];
        for ($i = 0; $i < 3; $i++) {
            $tmp = $this->createTempJpeg();
            $tmpFiles[] = $tmp;
            $imageFiles[] = $this->makeFileArray($tmp);
        }

        $res = $inst->index(
            $commentPostRepo,
            $commentLogRepo,
            app(CommentImageRepositoryInterface::class),
            app(CommentImageServiceInterface::class),
            $openChatPageRepo,
            $authStub,
            $recaptchaStub,
            $fileStorage,
            'dummy_token',
            1,
            'テストユーザー',
            'テスト本文（画像3枚）',
            $imageFiles[0],
            $imageFiles[1],
            $imageFiles[2]
        );

        $this->assertNotFalse($res);
        $json = json_decode($res->getBody(), true);
        $this->assertArrayHasKey('images', $json);
        $this->assertCount(3, $json['images']);

        // 各ファイルが実在・一意
        $uniqueNames = [];
        foreach ($json['images'] as $filename) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{32}\.webp$/', $filename);
            $this->assertFileExists($this->destDir . '/' . $filename);
            $uniqueNames[] = $filename;
        }
        $this->assertCount(3, array_unique($uniqueNames));

        // DBに3件保存
        $imageRepo = app(CommentImageRepositoryInterface::class);
        $dbImages = $imageRepo->getImagesByCommentId(self::TEST_COMMENT_ID);
        $this->assertCount(3, $dbImages);

        // クリーンアップ
        $this->cleanup($json['images']);
        foreach ($tmpFiles as $f) @unlink($f);
    }
}

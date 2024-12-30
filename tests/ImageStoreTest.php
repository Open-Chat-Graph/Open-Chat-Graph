<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Shadow\File\Image\ImageStore;

class ImageStoreTest extends TestCase
{
    private ImageStore $imageStore;

    protected function setUp(): void
    {
        $this->imageStore = new ImageStore();
    }

    public function testStoreImageFromGdImage(): void
    {
        // Create a dummy GD image
        $width = 100;
        $height = 100;
        $gdImage = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($gdImage, 255, 255, 255);
        imagefill($gdImage, 0, 0, $color);

        // Store the dummy image
        $destPath = '/var/www/html/public/images';
        $fileName = 'test_image';
        $imageType = ImageType::WEBP;
        $quality = 80;
        $result = $this->imageStore->storeImageFromGdImage($gdImage, $destPath, $fileName, $imageType, $quality);

        // Assert that the image was stored successfully
        $this->assertNotFalse($result);
        $this->assertFileExists($result);

        // Clean up the test image file
        unlink($result);
    }

    public function testStoreImageFromGdImageWithInvalidQuality(): void
    {
        // Create a dummy GD image
        $width = 100;
        $height = 100;
        $gdImage = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($gdImage, 255, 255, 255);
        imagefill($gdImage, 0, 0, $color);

        // Attempt to store the dummy image with an invalid quality value
        $destPath = '/var/www/html/public/images';
        $fileName = 'test_image';
        $imageType = ImageType::PNG;
        $quality = -1;

        // Assert that an InvalidArgumentException is thrown
        $this->expectException(InvalidArgumentException::class);
        $this->imageStore->storeImageFromGdImage($gdImage, $destPath, $fileName, $imageType, $quality);
    }

    public function testStoreImageFromGdImageWithUnwritableDestPath(): void
    {
        // Create a dummy GD image
        $width = 100;
        $height = 100;
        $gdImage = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($gdImage, 255, 255, 255);
        imagefill($gdImage, 0, 0, $color);

        // Attempt to store the dummy image to an unwritable destination path
        $destPath = '/var/www/html/public/im';
        $fileName = 'test_image';
        $imageType = ImageType::JPG;
        $quality = 80;

        // Assert that an InvalidArgumentException is thrown
        $this->expectException(InvalidArgumentException::class);
        $this->imageStore->storeImageFromGdImage($gdImage, $destPath, $fileName, $imageType, $quality);
    }
}

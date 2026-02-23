<?php

declare(strict_types=1);

namespace Shadow\File\Image;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
class GdImageFactory implements GdImageFactoryInterface
{
    public function createGdImage(string|array $imageData, ?int $maxWidth = null, ?int $maxHeight = null): \GdImage
    {
        [$imageData, $srcImage] = $this->imageCreateFromString($imageData);
        if (!$srcImage) {
            throw new \RuntimeException('Unable to create image from the given data.', 5001);
        }

        [$srcWidth, $srcHeight] = getimagesizefromstring($imageData);

        if (!$srcWidth || !$srcHeight) {
            throw new \RuntimeException('Unable to get size of the given image data.', 5002);
        }

        [$dstWidth, $dstHeight] = $this->getNewSize($srcWidth, $srcHeight, $maxWidth, $maxHeight);

        $dstImage = $this->imageCreate($dstWidth, $dstHeight);

        $this->processAlphaChannel($srcImage, $dstImage);

        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);

        return $dstImage;
    }

    protected function imageCreateFromString(string|array $imageData): array|false
    {
        if (is_string($imageData)) {
            return [$imageData, imagecreatefromstring($imageData)];
        }

        $isValidFile = is_array($imageData) && isset($imageData['tmp_name']) && file_exists($imageData['tmp_name']);
        if (!$isValidFile) {
            return [null, false];
        }

        $srcImageData = file_get_contents($imageData['tmp_name']);

        $gdImage = imagecreatefromstring($srcImageData);
        if (!$gdImage) {
            return [null, false];
        }

        $imageResource = $this->rotateImageIfNeeded($gdImage, $imageData['tmp_name']);
        if (!$imageResource) {
            return [$srcImageData, $gdImage];
        }

        ob_start();
        imagejpeg($imageResource, null, 100);
        return [ob_get_clean(), $imageResource];
    }

    protected function rotateImageIfNeeded(\GdImage $imageResource, string $imageData): \GdImage|false
    {
        if (exif_imagetype($imageData) !== IMAGETYPE_JPEG) {
            return false;
        }

        $exif = exif_read_data($imageData);
        $hasExif = $exif !== false && isset($exif['Orientation']);
        if (!$hasExif) {
            return false;
        }

        switch ($exif['Orientation']) {
            case 2:
                // Flip horizontal
                imageflip($imageResource, IMG_FLIP_HORIZONTAL);
                return $imageResource;
            case 3:
                // Rotate 180 degrees
                return imagerotate($imageResource, 180, 0);
            case 4:
                // Flip vertical
                imageflip($imageResource, IMG_FLIP_VERTICAL);
                return $imageResource;
            case 5:
                // Rotate 90 degrees and flip vertically
                $imageResource = imagerotate($imageResource, 90, 0);
                imageflip($imageResource, IMG_FLIP_VERTICAL);
                return $imageResource;
            case 6:
                // Rotate 270 degrees
                return imagerotate($imageResource, 270, 0);
            case 7:
                // Rotate 90 degrees and flip vertically
                $imageResource = imagerotate($imageResource, 270, 0);
                imageflip($imageResource, IMG_FLIP_VERTICAL);
                return $imageResource;
            case 8:
                // Rotate 270 degrees
                return imagerotate($imageResource, 90, 0);
            default:
                return $imageResource;
        }
    }

    protected function getNewSize(int $srcWidth, int $srcHeight, ?int $maxWidth, ?int $maxHeight): array
    {
        $dstWidth = $srcWidth;
        $dstHeight = $srcHeight;

        if ($maxWidth && $srcWidth > $maxWidth) {
            $dstWidth = $maxWidth;
            $dstHeight = intval(($dstWidth / $srcWidth) * $srcHeight);
        }

        if ($maxHeight && $dstHeight > $maxHeight) {
            $dstHeight = $maxHeight;
            $dstWidth = intval(($dstHeight / $srcHeight) * $srcWidth);
        }

        return [$dstWidth, $dstHeight];
    }

    protected function imageCreate(int $width, int $height): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);

        if (!$image) {
            throw new \RuntimeException('Failed to create a new image.', 5003);
        }

        return $image;
    }

    protected function processAlphaChannel(\GdImage $srcImage, \GdImage $dstImage)
    {
        $isAlpha = false;
        if (function_exists('imageistruecolor') && !imageistruecolor($srcImage)) {
            $isAlpha = imagecolortransparent($srcImage) != -1 || imagecolorstotal($srcImage) > 255;
        } else {
            $isAlpha = imagealphablending($srcImage, false);
            $color = imagecolorallocatealpha($srcImage, 0, 0, 0, 127);
            $isAlpha = $isAlpha || (imagecolorat($srcImage, 0, 0) & 0x7F000000) != 0x7F000000 || imagecolorclosestalpha($srcImage, 0, 0, 0, 127) != $color;
        }
        if ($isAlpha) {
            imagepalettetotruecolor($srcImage);
            imagealphablending($srcImage, true);
            imagesavealpha($srcImage, true);
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
        }
    }
}

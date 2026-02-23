<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\CommentRepositories\CommentImageRepositoryInterface;
use App\Services\Comment\CommentImageService;

class CommentImageThumbnailController
{
    private const THUMB_MAX_SIZE = 320;
    private const THUMB_QUALITY = 80;

    function index(string $filename, CommentImageRepositoryInterface $commentImageRepository)
    {
        $filename = basename($filename);
        if (!preg_match('/^[a-f0-9]{32}\.webp$/', $filename)) {
            return false;
        }

        $flag = $commentImageRepository->getCommentFlagByFilename($filename);
        if ($flag !== false && in_array($flag, [1, 2, 4])) {
            return false;
        }

        $filePath = CommentImageService::getImagePath($filename);
        if (!file_exists($filePath)) {
            return false;
        }

        $srcData = file_get_contents($filePath);
        $srcImage = imagecreatefromstring($srcData);
        if (!$srcImage) {
            return false;
        }

        $srcWidth = imagesx($srcImage);
        $srcHeight = imagesy($srcImage);

        $dstWidth = $srcWidth;
        $dstHeight = $srcHeight;

        if ($srcWidth > self::THUMB_MAX_SIZE) {
            $dstWidth = self::THUMB_MAX_SIZE;
            $dstHeight = intval(($dstWidth / $srcWidth) * $srcHeight);
        }

        if ($dstHeight > self::THUMB_MAX_SIZE) {
            $dstHeight = self::THUMB_MAX_SIZE;
            $dstWidth = intval(($dstHeight / $srcHeight) * $srcWidth);
        }

        $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);
        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
        imagedestroy($srcImage);

        header('Content-Type: image/webp');
        header('Cache-Control: public, max-age=31536000, immutable');
        imagewebp($dstImage, null, self::THUMB_QUALITY);
        imagedestroy($dstImage);
        exit;
    }
}

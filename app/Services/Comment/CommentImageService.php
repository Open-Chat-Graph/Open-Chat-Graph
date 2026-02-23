<?php

declare(strict_types=1);

namespace App\Services\Comment;

use Shadow\File\Image\GdImageFactoryInterface;
use Shadow\File\Image\ImageStoreInterface;
use Shared\MimimalCmsConfig;

class CommentImageService implements CommentImageServiceInterface
{
    private const DEST_PATH = '/comment-img';
    private const HIDDEN_PATH = '/../storage/comment-img-hidden';
    private const MAX_IMAGES = 3;

    function __construct(
        private GdImageFactoryInterface $gdImageFactory,
        private ImageStoreInterface $imageStore
    ) {
    }

    /** ファイル名先頭2文字をサブディレクトリ名として返す */
    static function getSubDir(string $filename): string
    {
        return substr($filename, 0, 2);
    }

    /** フルパスを構築する */
    static function getImagePath(string $filename): string
    {
        return MimimalCmsConfig::$publicDir . self::DEST_PATH . '/' . self::getSubDir($filename) . '/' . $filename;
    }

    /** 隠しディレクトリのフルパスを構築する */
    static function getHiddenImagePath(string $filename): string
    {
        return MimimalCmsConfig::$publicDir . self::HIDDEN_PATH . '/' . self::getSubDir($filename) . '/' . $filename;
    }

    function processAndStore(array $files): array
    {
        $files = array_slice($files, 0, self::MAX_IMAGES);
        $filenames = [];

        foreach ($files as $file) {
            $gdImage = $this->gdImageFactory->createGdImage($file);
            $filename = bin2hex(random_bytes(16));
            $subDir = self::getSubDir($filename);
            $destDir = MimimalCmsConfig::$publicDir . self::DEST_PATH . '/' . $subDir;

            if (!is_dir($destDir)) {
                mkdir($destDir, 0775, true);
            }

            $this->imageStore->storeImageFromGdImage(
                $gdImage,
                $destDir,
                $filename,
                \ImageType::WEBP,
                80
            );
            imagedestroy($gdImage);
            $filenames[] = $filename . '.webp';
        }

        return $filenames;
    }

    /** 画像をpublic外に移動（flag=4用） */
    function hideImages(array $filenames): void
    {
        foreach ($filenames as $filename) {
            $src = self::getImagePath($filename);
            if (!file_exists($src)) continue;

            $subDir = self::getSubDir($filename);
            $destDir = MimimalCmsConfig::$publicDir . self::HIDDEN_PATH . '/' . $subDir;
            if (!is_dir($destDir)) {
                mkdir($destDir, 0775, true);
            }

            rename($src, $destDir . '/' . $filename);
        }
    }

    /** 画像をpublicに復元（flag=0復元用） */
    function restoreImages(array $filenames): void
    {
        foreach ($filenames as $filename) {
            $subDir = self::getSubDir($filename);
            $src = MimimalCmsConfig::$publicDir . self::HIDDEN_PATH . '/' . $subDir . '/' . $filename;
            if (!file_exists($src)) continue;

            $destDir = MimimalCmsConfig::$publicDir . self::DEST_PATH . '/' . $subDir;
            if (!is_dir($destDir)) {
                mkdir($destDir, 0775, true);
            }

            rename($src, $destDir . '/' . $filename);
        }
    }

    function deleteImages(array $filenames): void
    {
        foreach ($filenames as $filename) {
            $path = self::getImagePath($filename);
            if (file_exists($path)) {
                unlink($path);
                continue;
            }

            $hiddenPath = self::getHiddenImagePath($filename);
            if (file_exists($hiddenPath)) {
                unlink($hiddenPath);
            }
        }
    }

    function calculateStorageSize(array $deletedFilenames): array
    {
        $totalSize = 0;
        $deletedSize = 0;

        // 全体容量（公開 + 隠しディレクトリ）
        foreach ([self::DEST_PATH, self::HIDDEN_PATH] as $path) {
            $dir = MimimalCmsConfig::$publicDir . $path;
            $files = glob($dir . '/*/*.webp');
            if ($files) {
                foreach ($files as $file) {
                    $totalSize += filesize($file);
                }
            }
        }

        // 削除済み画像の容量
        foreach ($deletedFilenames as $filename) {
            $path = self::getImagePath($filename);
            if (file_exists($path)) {
                $deletedSize += filesize($path);
                continue;
            }

            $hiddenPath = self::getHiddenImagePath($filename);
            if (file_exists($hiddenPath)) {
                $deletedSize += filesize($hiddenPath);
            }
        }

        return [
            'total_size' => $totalSize,
            'deleted_size' => $deletedSize,
        ];
    }
}

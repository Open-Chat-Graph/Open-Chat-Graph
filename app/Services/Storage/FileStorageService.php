<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Config\FileStorageServiceConfig;
use Shared\MimimalCmsConfig;

/**
 * ファイル書き込み操作の実装クラス
 *
 * タイムスタンプの偽装（faketime使用時）に対応するため、
 * ファイル作成時にtouch()を呼び出す
 *
 * パスが@で始まる場合は自動的にgetStorageFilePath()を通してストレージパスを解決する
 */
class FileStorageService implements FileStorageInterface
{
    /**
     * ストレージファイルのパスを取得
     *
     * @param string $storageFileName ストレージファイルキー
     * @return string ファイルの絶対パス
     */
    public function getStorageFilePath(string $storageFileName): string
    {
        return FileStorageServiceConfig::$storageDir[MimimalCmsConfig::$urlRoot]
            . FileStorageServiceConfig::$storageFiles[$storageFileName];
    }

    /**
     * パスを解決する（@で始まる場合はgetStorageFilePath()を通す）
     *
     * @param string $filepath ファイルパス（@で始まる場合はストレージキー名）
     * @return string 解決されたファイルパス
     */
    private function resolvePath(string $filepath): string
    {
        if (str_starts_with($filepath, '@')) {
            return $this->getStorageFilePath(substr($filepath, 1));
        }
        return $filepath;
    }

    /**
     * faketimeが有効かどうかを判定（初回のみチェック、以降はキャッシュを使用）
     */
    private function isFaketimeEnabled(): bool
    {
        return function_exists('getenv') && getenv('FAKETIME') !== false;
    }

    /**
     * {@inheritDoc}
     */
    public function safeFileRewrite(string $filepath, string $content): void
    {
        $filepath = $this->resolvePath($filepath);
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            throw new \RuntimeException("Directory does not exist: {$dir}");
        }

        $tempFile = tempnam($dir, 'tmp_');
        if ($tempFile === false) {
            throw new \RuntimeException("Failed to create temporary file in: {$dir}");
        }

        if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
            throw new \RuntimeException("Failed to write to temporary file: {$tempFile}");
        }

        chmod($tempFile, 0644);

        // faketime使用時のみタイムスタンプを現在時刻に設定（偽装された時刻が適用される）
        if ($this->isFaketimeEnabled()) {
            touch($tempFile);
        }

        if (!rename($tempFile, $filepath)) {
            throw new \RuntimeException("Failed to rename {$tempFile} to {$filepath}");
        }
    }

    /**
     * {@inheritDoc}
     */
    public function saveSerializedFile(string $filepath, mixed $data): void
    {
        // resolvePathはsafeFileRewrite内で呼ばれるため、ここでは不要
        $serialized = gzencode(serialize($data));
        $this->safeFileRewrite($filepath, $serialized);
    }

    /**
     * {@inheritDoc}
     */
    public function putContents(string $filepath, string $content): void
    {
        $filepath = $this->resolvePath($filepath);
        if (file_put_contents($filepath, $content) === false) {
            throw new \RuntimeException("Failed to write to file: {$filepath}");
        }

        // faketime使用時のみタイムスタンプを現在時刻に設定（偽装された時刻が適用される）
        if ($this->isFaketimeEnabled()) {
            touch($filepath);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getContents(string $filepath): string
    {
        $filepath = $this->resolvePath($filepath);
        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$filepath}");
        }
        return $content;
    }

    /**
     * {@inheritDoc}
     */
    public function getSerializedFile(string $filepath): mixed
    {
        $filepath = $this->resolvePath($filepath);

        if (!file_exists($filepath)) {
            return false;
        }

        $data = file_get_contents($filepath);
        if ($data === false) {
            return false;
        }

        return unserialize(gzdecode($data));
    }
}

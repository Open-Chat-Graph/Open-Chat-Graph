<?php

declare(strict_types=1);

namespace App\Services\RankingPosition\Store;

use App\Services\OpenChat\Dto\OpenChatDto;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;

abstract class AbstractRankingPositionStore
{
    /**
     * @var OpenChatDto[] $apiDtoCache
     */
    protected array $apiDtoCache = [];

    abstract function filePath(): string;

    function addApiDto(OpenChatDto $apiDto)
    {
        $this->apiDtoCache[] = $apiDto;
    }

    function getCacheCount(): int
    {
        return count($this->apiDtoCache);
    }

    function clearAllCacheDataAndSaveCurrentCategoryApiDataCache(string $category): void
    {
        saveSerializedFile(
            $this->filePath() . "/{$category}.dat",
            $this->apiDtoCache,
        );

        $this->apiDtoCache = [];
    }

    /**
     * @return array{ 0: string, 1: OpenChatDto[] }
     */
    function getStorageData(string $category): array
    {
        $file = $this->filePath() . "/{$category}.dat";
        $data = getUnserializedFile($file);
        if (!is_array($data)) {
            throw new \RuntimeException('invalid ranking data file: ' . $file);
        }

        $fileTime = $this->getModifiedFileTime($file);
        return [$fileTime->format('Y-m-d H:i:s'), $data];
    }

    function deleteApiDtoStorageAll(): void
    {
        deleteStorageFileAll($this->filePath(), true);
    }

    function getFileDateTime(string $category = '0'): \DateTime
    {
        if (!file_exists($this->filePath() . "/{$category}.dat")) {
            return new \DateTime('2024-02-17 00:00:00');
        }

        return $this->getModifiedFileTime($this->filePath() . "/{$category}.dat");
    }

    protected function getModifiedFileTime(string $fileName): \DateTime
    {
        return OpenChatServicesUtility::getModifiedCronTime(filemtime($fileName));
    }
}

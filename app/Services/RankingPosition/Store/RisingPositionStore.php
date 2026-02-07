<?php

declare(strict_types=1);

namespace App\Services\RankingPosition\Store;

class RisingPositionStore extends AbstractRankingPositionStore
{
    function filePath(): string
    {
        return $this->fileStorage->getStorageFilePath('openChatRisingPositionDir');
    }
}

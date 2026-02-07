<?php

declare(strict_types=1);

namespace App\Services\RankingPosition\Store;

class RankingPositionStore extends AbstractRankingPositionStore
{
    function filePath(): string
    {
        return $this->fileStorage->getStorageFilePath('openChatRankingPositionDir');
    }
}

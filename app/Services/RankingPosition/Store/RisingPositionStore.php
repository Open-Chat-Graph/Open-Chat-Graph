<?php

declare(strict_types=1);

namespace App\Services\RankingPosition\Store;

use App\Services\Storage\FileStorageService;

class RisingPositionStore extends AbstractRankingPositionStore
{
    function filePath(): string
    {
        return FileStorageService::getStorageFilePath('openChatRisingPositionDir');
    }
}

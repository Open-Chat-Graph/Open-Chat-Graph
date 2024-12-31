<?php

declare(strict_types=1);

namespace App\Services\RankingPosition\Store;

use App\Config\AppConfig;

class RisingPositionStore extends AbstractRankingPositionStore
{
    function __construct()
    {
        $this->filePath = getStorageFilePath(AppConfig::STORAGE_FILES['openChatRisingPositionDir']);
    }
}

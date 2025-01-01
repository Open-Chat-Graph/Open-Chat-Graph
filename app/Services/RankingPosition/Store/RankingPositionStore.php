<?php

declare(strict_types=1);

namespace App\Services\RankingPosition\Store;

use App\Config\AppConfig;

class RankingPositionStore extends AbstractRankingPositionStore
{
    function filePath(): string
    {
        return AppConfig::getStorageFilePath('openChatRankingPositionDir');
    }
}

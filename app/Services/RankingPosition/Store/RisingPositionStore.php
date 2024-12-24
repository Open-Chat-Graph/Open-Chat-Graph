<?php

declare(strict_types=1);

namespace App\Services\RankingPosition\Store;

use App\Config\AppConfig;

class RisingPositionStore extends AbstractRankingPositionStore
{
    function __construct()
    {
        $this->filePath = AppConfig::$OPEN_CHAT_RISING_POSITION_DIR;
    }
}

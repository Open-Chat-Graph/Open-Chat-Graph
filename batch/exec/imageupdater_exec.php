<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Admin\AdminTool;
use App\Services\Cron\Utility\CronUtility;
use App\Services\OpenChat\OpenChatImageUpdater;
use Shared\MimimalCmsConfig;

set_time_limit(3600 * 10);

try {
    if (isset($argv[1]) && $argv[1]) {
        MimimalCmsConfig::$urlRoot = $argv[1];
    }

    /**
     * @var OpenChatImageUpdater $img
     */
    $img = app(OpenChatImageUpdater::class);

    AdminTool::sendDiscordNotify('imageUpdateAll start');

    $result = $img->imageUpdateAll(false);

    AdminTool::sendDiscordNotify('imageUpdateAll done: ' . $result);
} catch (\Throwable $e) {
    CronUtility::addCronLog($e->__toString());
    AdminTool::sendDiscordNotify($e->__toString());
}

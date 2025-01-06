<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Admin\AdminTool;
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

    AdminTool::sendLineNofity('imageUpdateAll start');

    $result = $img->imageUpdateAll(false);

    AdminTool::sendLineNofity('imageUpdateAll done: ' . $result);
} catch (\Throwable $e) {
    addCronLog($e->__toString());
    AdminTool::sendLineNofity($e->__toString());
}

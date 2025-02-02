<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Admin\AdminTool;
use App\Services\Recommend\RecommendUpdater;
use Shared\MimimalCmsConfig;

set_time_limit(3600 * 10);

try {
    if (isset($argv[1]) && $argv[1]) {
        MimimalCmsConfig::$urlRoot = $argv[1];
    }

    /**
     * @var RecommendUpdater $recommendUpdater
     */
    $recommendUpdater = app(RecommendUpdater::class);
    AdminTool::sendLineNofity('updateRecommendTables start');
    $recommendUpdater->updateRecommendTables(false);
    AdminTool::sendLineNofity('updateRecommendTables done');
} catch (\Throwable $e) {
    addCronLog($e->__toString());
    AdminTool::sendLineNofity($e->__toString());
}

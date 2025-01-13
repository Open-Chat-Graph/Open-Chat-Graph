<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Admin\AdminTool;
use App\Services\Recommend\RecommendUpdater;

set_time_limit(3600 * 10);

/**
 * @var RecommendUpdater $oc
 */
$oc = app(RecommendUpdater::class);


try {
    AdminTool::sendLineNofity('oc start');

    $oc->updateRecommendTables(false);

    AdminTool::sendLineNofity('oc done');
} catch (\Throwable $e) {
    addCronLog($e->__toString());
    AdminTool::sendLineNofity($e->__toString());
}

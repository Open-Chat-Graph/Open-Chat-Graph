<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Cron\Utility\BatchScriptLauncher;
use App\Services\Recommend\StaticData\UpdateRecommendStaticDataService;
use Shared\MimimalCmsConfig;

set_time_limit(3600 * 2);

if (isset($argv[1]) && $argv[1]) {
    MimimalCmsConfig::$urlRoot = $argv[1];
}

(new BatchScriptLauncher)->run(function () {
    /**
     * @var UpdateRecommendStaticDataService $service
     */
    $service = app(UpdateRecommendStaticDataService::class);
    $service->handle();
});

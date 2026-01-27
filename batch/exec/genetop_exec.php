<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Config\AppConfig;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Utility\CronUtility;
use App\Services\Recommend\StaticData\RecommendStaticDataGenerator;
use App\Services\StaticData\StaticDataGenerator;
use Shared\MimimalCmsConfig;

try {
    set_time_limit(3600);

    if (isset($argv[1]) && $argv[1]) {
        MimimalCmsConfig::$urlRoot = $argv[1];
    }

    /**
     * @var StaticDataGenerator $staticDataGenerator
     * @var RecommendStaticDataGenerator $recommendStaticDataGenerator
     */
    $staticDataGenerator = app(StaticDataGenerator::class);
    $recommendStaticDataGenerator = app(RecommendStaticDataGenerator::class);

    AdminTool::sendDiscordNotify('staticDataGenerator start');
    $staticDataGenerator->updateStaticData();
    AdminTool::sendDiscordNotify("staticDataGenerator done\nrecommendStaticDataGenerator start");
    $recommendStaticDataGenerator->updateStaticData();
    AdminTool::sendDiscordNotify('recommendStaticDataGenerator done');
    
    touch(AppConfig::getStorageFilePath('hourlyCronUpdatedAtDatetime'));
} catch (\Throwable $e) {
    AdminTool::sendDiscordNotify($e->__toString());
    CronUtility::addCronLog($e->__toString());
}

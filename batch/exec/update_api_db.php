<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Admin\AdminTool;
use ExceptionHandler\ExceptionHandler;

set_time_limit(3600 * 10);

try {
    // Create an instance of OcreviewApiDataImporter
    $importer = app(\App\Services\Cron\OcreviewApiDataImporter::class);

    // Execute the import process
    $importer->execute();
} catch (\Throwable $e) {
    addCronLog($e->__toString());
    AdminTool::sendDiscordNotify($e->__toString());
    ExceptionHandler::errorLog($e);
}

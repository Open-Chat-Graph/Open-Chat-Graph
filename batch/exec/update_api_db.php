<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Cron\Utility\BatchScriptLauncher;

set_time_limit(3600 * 10);

(new BatchScriptLauncher)->run(function () {
    // Create an instance of OcreviewApiDataImporter
    $importer = app(\App\Services\Cron\OcreviewApiDataImporter::class);

    // Execute the import process
    $importer->execute();
});

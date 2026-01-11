<?php

use PHPUnit\Framework\TestCase;
use App\Services\Cron\OcreviewApiDataImporter;
use Shadow\DB;

class OcreviewApiDataImporterTest extends TestCase
{
    public function testExecute()
    {
        // Set a long execution time limit for the test
        set_time_limit(3600 * 1);

        // Create an instance of OcreviewApiDataImporter
        $importer = app(OcreviewApiDataImporter::class);

        // Execute the import process
        $importer->execute();

        // Assert that the import process completed successfully
        $this->assertTrue(true);
    }
}

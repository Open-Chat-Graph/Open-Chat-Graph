<?php

declare(strict_types=1);

use App\Services\Statistics\DownloadCsvService;
use PHPUnit\Framework\TestCase;

class DownloadCsvServiceTest extends TestCase
{
    private DownloadCsvService $inst;

    public function test()
    {
        $this->inst = app(DownloadCsvService::class);

        $r = $this->inst->buildData(2, 6);

        debug($r);

        $this->assertIsBool(true);
    }
}

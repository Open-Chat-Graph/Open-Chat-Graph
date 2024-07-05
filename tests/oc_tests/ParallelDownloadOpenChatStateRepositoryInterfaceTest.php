<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Models\Repositories\ParallelDownloadOpenChatStateRepositoryInterface;
use PHPUnit\Framework\TestCase;

class ParallelDownloadOpenChatStateRepositoryInterfaceTest extends TestCase
{
    private ParallelDownloadOpenChatStateRepositoryInterface $inst;

    public function test()
    {
        $this->inst = app(ParallelDownloadOpenChatStateRepositoryInterface::class);

        $r = $this->inst->cleanUpAll('0');
        debug($r);

        $this->assertTrue(true);
    }
}

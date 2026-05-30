<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Models/Repositories/Log/test/LogRepositoryTest.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Models\Repositories\DB;
use App\Models\Repositories\Log\LogRepository;

class LogRepositoryTest extends TestCase
{
    public LogRepository $log;

    public function test()
    {
        $this->log = app(LogRepository::class);
        
        $result = $this->log->getRecentLog();
        debug($result);

        $this->assertIsString($result);
    }
}

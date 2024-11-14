<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Models\Importer\SqlInsertWithBindValue;
use PHPUnit\Framework\TestCase;

class AdminToolTest extends TestCase
{
    public SqlInsertWithBindValue $sqlInsertWithBindValue;

    public function setUp(): void
    {
        $this->sqlInsertWithBindValue = app(SqlInsertWithBindValue::class);
    }

    public function test()
    {
        $this->assertTrue(true);
    }
}
